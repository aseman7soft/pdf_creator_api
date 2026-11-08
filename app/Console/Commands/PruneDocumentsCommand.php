<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `php artisan documents:prune`
 *
 * Deletes generated PDF files whose document has expired (expires_at) or is
 * older than the configured retention period, then soft-deletes the document
 * row so it can still be audited. Supports --dry-run and an explicit --days
 * override; the retention default comes from the documents.retention_days
 * setting falling back to config('pdf.retention_days').
 */
final class PruneDocumentsCommand extends Command
{
    protected $signature = 'documents:prune
        {--dry-run : فقط شمارش اسناد منقضی بدون حذف فایل یا رکورد}
        {--days= : مدت نگهداری بر حسب روز (پیش‌فرض از تنظیمات)}';

    protected $description = 'پاک‌سازی اسناد تولیدشده منقضی‌شده و فایل‌های PDF مربوط به آن‌ها';

    public function handle(DocumentStorageService $storage): int
    {
        $days = $this->retentionDays();
        $cutoff = now()->subDays($days);

        $documents = GeneratedDocument::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('expires_at')->where('expires_at', '<=', now());
                })->orWhere(function ($inner) use ($cutoff): void {
                    $inner->whereNull('expires_at')
                        ->whereNotNull('generated_at')
                        ->where('generated_at', '<=', $cutoff);
                });
            })
            ->orderBy('id');

        if ($this->option('dry-run')) {
            $count = (clone $documents)->count();
            $withFiles = (clone $documents)->whereNotNull('file_path')->count();

            $this->info('حالت آزمایشی (dry-run): هیچ فایلی حذف نشد.');
            $this->line('مدت نگهداری: '.$days.' روز (پیش از '.$cutoff->toDateString().')');
            $this->line('اسناد منقضی: '.$count.' — با فایل PDF: '.$withFiles);

            return self::SUCCESS;
        }

        $deletedDocuments = 0;
        $deletedFiles = 0;
        $errors = 0;

        foreach ($documents->cursor() as $document) {
            /** @var GeneratedDocument $document */
            if ($document->status === DocumentStatus::Processing) {
                continue;
            }

            try {
                if ($storage->delete($document)) {
                    $deletedFiles++;
                }
            } catch (Throwable $exception) {
                $errors++;
                Log::warning('Expired document file could not be deleted', [
                    'document_id' => $document->getKey(),
                    'exception' => $exception::class,
                ]);
            }

            try {
                $document->delete();
                $deletedDocuments++;
            } catch (Throwable $exception) {
                $errors++;
                Log::warning('Expired document row could not be soft-deleted', [
                    'document_id' => $document->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->info('پاک‌سازی اسناد منقضی انجام شد.');
        $this->line('مدت نگهداری: '.$days.' روز');
        $this->line('رکوردهای حذف‌شده (soft delete): '.$deletedDocuments);
        $this->line('فایل‌های PDF حذف‌شده: '.$deletedFiles);

        if ($errors > 0) {
            $this->warn('خطا در حذف '.$errors.' مورد؛ جزئیات در لاگ ثبت شد.');
        }

        return self::SUCCESS;
    }

    private function retentionDays(): int
    {
        $option = $this->option('days');

        if ($option !== null && $option !== '' && is_numeric($option)) {
            return max(1, (int) $option);
        }

        $configured = setting('documents.retention_days', (int) config('pdf.retention_days', 30));

        return max(1, (int) $configured);
    }
}
