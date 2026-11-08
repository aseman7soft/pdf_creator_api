<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PurgeLogsRequest;
use App\Models\ApiProject;
use App\Models\ApiRequestLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LogController
{
    private const MAX_LOG_BYTES = 2_000_000;

    private const MAX_LOG_LINES = 2000;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ApiRequestLog::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'project_id' => ['nullable', 'integer', 'exists:api_projects,id'],
            'status_class' => ['nullable', 'in:2xx,3xx,4xx,5xx'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = ApiRequestLog::query()->with(['project:id,name', 'apiKey:id,name,key_prefix']);

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('request_id', 'like', $term)
                    ->orWhere('path', 'like', $term)
                    ->orWhere('error_code', 'like', $term);
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('api_project_id', $filters['project_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (! empty($filters['status_class'])) {
            [$from, $to] = match ($filters['status_class']) {
                '2xx' => [200, 299],
                '3xx' => [300, 399],
                '4xx' => [400, 499],
                '5xx' => [500, 599],
            };

            $query->whereBetween('status_code', [$from, $to]);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        return view('logs.index', [
            'logs' => $query->latest('id')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
            'projects' => ApiProject::query()->orderBy('name')->get(['id', 'name']),
            'totalCount' => ApiRequestLog::query()->count(),
        ]);
    }

    public function show(ApiRequestLog $log): View
    {
        Gate::authorize('viewAny', ApiRequestLog::class);

        $log->load(['project', 'apiKey', 'template' => fn ($builder) => $builder->withTrashed(), 'document']);

        return view('logs.show', [
            'log' => $log,
        ]);
    }

    public function purge(PurgeLogsRequest $request): RedirectResponse
    {
        $days = (int) $request->validated()['days'];

        $deleted = ApiRequestLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        return back()->with(
            'success',
            'تعداد '.fa_num($deleted).' رکورد لاگ قدیمی‌تر از '.fa_num($days).' روز حذف شد.',
        );
    }

    public function appLog(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() === true, 403);

        $filters = $request->validate([
            'level' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $path = storage_path('logs/laravel.log');
        $exists = is_file($path);
        $fileSize = $exists ? (int) filesize($path) : 0;

        $entries = $exists ? $this->parseLogEntries($this->readTail($path)) : [];
        $totalEntries = count($entries);

        $level = strtoupper(trim((string) ($filters['level'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($level !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => $entry['level'] === $level,
            ));
        }

        if ($search !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => mb_stripos(implode("\n", $entry['lines']), $search) !== false,
            ));
        }

        $entries = array_slice($entries, -self::MAX_LOG_LINES);

        return view('logs.app', [
            'entries' => $entries,
            'filters' => $filters,
            'levels' => ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'],
            'path' => $path,
            'exists' => $exists,
            'fileSize' => $fileSize,
            'totalEntries' => $totalEntries,
            'maxLines' => self::MAX_LOG_LINES,
        ]);
    }

    /**
     * @return list<array{timestamp: string, env: string, level: string, lines: list<string>}>
     */
    private function parseLogEntries(array $lines): array
    {
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[^\]]*)\]\s+(\w+)\.(\w+):\s?(.*)$/', $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $matches[1],
                    'env' => $matches[2],
                    'level' => strtoupper($matches[3]),
                    'lines' => [$line],
                ];

                continue;
            }

            if ($current === null) {
                $current = [
                    'timestamp' => '',
                    'env' => '',
                    'level' => 'UNKNOWN',
                    'lines' => [$line],
                ];
            } else {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function readTail(string $path): array
    {
        $size = (int) filesize($path);

        if ($size === 0) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $offset = max(0, $size - self::MAX_LOG_BYTES);

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $content = (string) stream_get_contents($handle);
        fclose($handle);

        if ($offset > 0) {
            $firstBreak = strpos($content, "\n");

            if ($firstBreak !== false) {
                $content = substr($content, $firstBreak + 1);
            }
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];

        if (count($lines) > self::MAX_LOG_LINES) {
            $lines = array_slice($lines, -self::MAX_LOG_LINES);
        }

        return $lines;
    }
}
