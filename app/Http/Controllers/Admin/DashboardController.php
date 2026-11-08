<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Http\Controllers\Admin\Concerns\InteractsWithGenerationEngine;
use App\Models\ApiProject;
use App\Models\ApiRequestLog;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DashboardController
{
    use InteractsWithGenerationEngine;

    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => $this->stats(),
            'chart' => $this->documentsChart(),
            'recentDocuments' => $this->recentDocuments(),
            'recentLogs' => $this->recentLogs(),
            'queue' => $this->queueHealth(),
            'gotenberg' => $this->gotenbergHealth(),
            'topTemplates' => $this->topTemplates(),
        ]);
    }

    /**
     * @return array<string, int|float|null>
     */
    private function stats(): array
    {
        return [
            'documents_total' => $this->safe(fn (): int => GeneratedDocument::query()->count(), 0),
            'documents_today' => $this->safe(
                fn (): int => GeneratedDocument::query()->whereDate('created_at', now()->toDateString())->count(),
                0,
            ),
            'documents_completed' => $this->safe(
                fn (): int => GeneratedDocument::query()->where('status', DocumentStatus::Completed->value)->count(),
                0,
            ),
            'documents_failed' => $this->safe(
                fn (): int => GeneratedDocument::query()->where('status', DocumentStatus::Failed->value)->count(),
                0,
            ),
            'requests_total' => $this->safe(fn (): int => ApiRequestLog::query()->count(), 0),
            'requests_successful' => $this->safe(fn (): int => ApiRequestLog::query()->successful()->count(), 0),
            'requests_failed' => $this->safe(fn (): int => ApiRequestLog::query()->failed()->count(), 0),
            'projects_active' => $this->safe(fn (): int => ApiProject::query()->active()->count(), 0),
            'templates_active' => $this->safe(fn (): int => Template::query()->active()->count(), 0),
            'avg_generation_ms' => $this->safe(
                fn (): float => (float) DocumentJob::query()
                    ->where('status', JobStatus::Completed->value)
                    ->whereNotNull('duration_ms')
                    ->avg('duration_ms'),
                0,
            ),
        ];
    }

    /**
     * Document volume for the last seven days (oldest first).
     *
     * @return list<array{label: string, date: string, count: int, height: int}>
     */
    private function documentsChart(): array
    {
        $start = now()->subDays(6)->startOfDay();
        $counts = [];

        try {
            $rows = GeneratedDocument::query()
                ->where('created_at', '>=', $start)
                ->get(['created_at'])
                ->groupBy(fn (GeneratedDocument $document): string => $document->created_at?->toDateString() ?? '')
                ->map(fn ($group): int => $group->count());
            $counts = $rows->all();
        } catch (QueryException) {
            $counts = [];
        }

        $max = max(1, (int) (count($counts) > 0 ? max($counts) : 0));
        $points = [];

        for ($index = 0; $index < 7; $index++) {
            $day = $start->copy()->addDays($index);
            $key = $day->toDateString();
            $count = (int) ($counts[$key] ?? 0);

            $points[] = [
                'label' => jdate($day, 'j F'),
                'date' => $key,
                'count' => $count,
                'height' => (int) round(($count / $max) * 100),
            ];
        }

        return $points;
    }

    /**
     * @return Collection<int, GeneratedDocument>
     */
    private function recentDocuments()
    {
        return $this->safe(
            fn () => GeneratedDocument::query()
                ->with(['project:id,name', 'template:id,name'])
                ->latest('id')
                ->limit(8)
                ->get(),
            collect(),
        );
    }

    /**
     * @return Collection<int, ApiRequestLog>
     */
    private function recentLogs()
    {
        return $this->safe(
            fn () => ApiRequestLog::query()
                ->with(['project:id,name'])
                ->latest('id')
                ->limit(8)
                ->get(),
            collect(),
        );
    }

    /**
     * @return array{jobs: int|null, failed_jobs: int|null, pending: int, processing: int, failed: int, completed_today: int}
     */
    private function queueHealth(): array
    {
        return [
            'jobs' => $this->safe(fn (): int => (int) DB::table('jobs')->count(), null),
            'failed_jobs' => $this->safe(fn (): int => (int) DB::table('failed_jobs')->count(), null),
            'pending' => $this->safe(
                fn (): int => DocumentJob::query()->where('status', JobStatus::Pending->value)->count(),
                0,
            ),
            'processing' => $this->safe(
                fn (): int => DocumentJob::query()->where('status', JobStatus::Processing->value)->count(),
                0,
            ),
            'failed' => $this->safe(
                fn (): int => DocumentJob::query()->where('status', JobStatus::Failed->value)->count(),
                0,
            ),
            'completed_today' => $this->safe(
                fn (): int => DocumentJob::query()
                    ->where('status', JobStatus::Completed->value)
                    ->whereDate('updated_at', now()->toDateString())
                    ->count(),
                0,
            ),
        ];
    }

    /**
     * @return Collection<int, array{template: Template, total: int}>
     */
    private function topTemplates()
    {
        return $this->safe(function (): Collection {
            return GeneratedDocument::query()
                ->select('template_id', DB::raw('count(*) as total'))
                ->whereNotNull('template_id')
                ->groupBy('template_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(function (GeneratedDocument $row): array {
                    return [
                        'template' => Template::query()->withTrashed()->find($row->template_id),
                        'total' => (int) $row->getAttribute('total'),
                    ];
                })
                ->filter(fn (array $item): bool => $item['template'] instanceof Template)
                ->values();
        }, collect());
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $default
     * @return TValue
     */
    private function safe(callable $callback, mixed $default): mixed
    {
        try {
            return $callback();
        } catch (QueryException|Throwable) {
            return $default;
        }
    }
}
