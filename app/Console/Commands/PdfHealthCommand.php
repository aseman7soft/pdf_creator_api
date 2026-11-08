<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gotenberg\GotenbergService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * `php artisan pdf:health`
 *
 * Checks the three critical dependencies of the document pipeline (database,
 * private document storage, Gotenberg) plus a queue summary. Exits non-zero
 * when a critical check fails so systemd/uptime probes can alert.
 */
final class PdfHealthCommand extends Command
{
    protected $signature = 'pdf:health
        {--json : نمایش خروجی به صورت JSON}';

    protected $description = 'بررسی سلامت سرویس تولید اسناد (پایگاه‌داده، ذخیره‌سازی، Gotenberg و صف)';

    public function handle(GotenbergService $gotenberg): int
    {
        $startedAt = microtime(true);

        $database = $this->checkDatabase();
        $storage = $this->checkStorage();
        $gotenbergHealth = $gotenberg->health();
        $queue = $this->queueSummary();

        $criticalOk = $database['ok'] && $storage['ok'] && $gotenbergHealth['ok'];

        $report = [
            'ok' => $criticalOk,
            'checks' => [
                'database' => $database,
                'storage' => $storage,
                'gotenberg' => $gotenbergHealth,
            ],
            'queue' => $queue,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checked_at' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTable($report);
        }

        if (! $criticalOk) {
            Log::warning('PDF service health check failed', [
                'database' => $database['ok'],
                'storage' => $storage['ok'],
                'gotenberg' => $gotenbergHealth['ok'],
            ]);
        }

        return $criticalOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{ok: bool, latency_ms: int, error: string|null}
     */
    private function checkDatabase(): array
    {
        $startedAt = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => null,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => 'اتصال به پایگاه‌داده برقرار نشد.',
            ];
        }
    }

    /**
     * @return array{ok: bool, disk: string, latency_ms: int, error: string|null}
     */
    private function checkStorage(): array
    {
        $diskName = (string) config('pdf.disk', 'local');
        $startedAt = microtime(true);
        $probePath = 'health/probe-'.bin2hex(random_bytes(8)).'.txt';

        try {
            $disk = Storage::disk($diskName);
            $written = $disk->put($probePath, 'pdf-service-health-check');
            $exists = $written !== false && $disk->exists($probePath);
            $disk->delete($probePath);

            return [
                'ok' => (bool) $exists,
                'disk' => $diskName,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $exists ? null : 'نوشتن فایل آزمایشی در دیسک اسناد ناموفق بود.',
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'disk' => $diskName,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => 'دسترسی به دیسک ذخیره‌سازی اسناد ممکن نیست.',
            ];
        }
    }

    /**
     * @return array{connection: string, name: string, driver: string|null, pending: int|null, failed: int|null}
     */
    private function queueSummary(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $name = (string) config('pdf.queue.name', 'documents');
        $configuration = config('queue.connections.'.$connection);
        $driver = is_array($configuration) ? (string) ($configuration['driver'] ?? '') : null;
        $pending = null;
        $failed = null;

        try {
            if ($driver === 'database' && Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }

            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            // Queue metrics are informational; never fail the health command.
        }

        return [
            'connection' => $connection,
            'name' => $name,
            'driver' => $driver,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * @param  array{ok: bool, checks: array<string, array<string, mixed>>, queue: array<string, mixed>, duration_ms: int}  $report
     */
    private function renderTable(array $report): void
    {
        $status = static fn (bool $ok): string => $ok ? 'OK' : 'FAIL';

        $this->line('سرویس تولید اسناد — گزارش سلامت');
        $this->line(str_repeat('-', 44));
        $this->line('پایگاه‌داده: '.$status((bool) $report['checks']['database']['ok']));
        $this->line('ذخیره‌سازی ('.$report['checks']['storage']['disk'].'): '.$status((bool) $report['checks']['storage']['ok']));
        $this->line('Gotenberg: '.$status((bool) $report['checks']['gotenberg']['ok']));
        $this->line('صف: '.$report['queue']['connection'].' / '.$report['queue']['name'].' (driver: '.($report['queue']['driver'] ?? '—').')');

        if ($report['queue']['pending'] !== null) {
            $this->line('صف در انتظار: '.$report['queue']['pending']);
        }

        if ($report['queue']['failed'] !== null) {
            $this->line('کارهای ناموفق: '.$report['queue']['failed']);
        }

        $this->line('مدت بررسی: '.$report['duration_ms'].' میلی‌ثانیه');
        $this->line($report['ok'] ? 'وضعیت کلی: سالم' : 'وضعیت کلی: ناسالم');
    }
}
