<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Events\DocumentGenerationFailed;
use App\Exceptions\DocumentGenerationException;
use App\Exceptions\GotenbergException;
use App\Exceptions\TemplateRenderException;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asynchronous document generation job.
 *
 * - tries/timeout/backoff come from config (pdf.queue.*),
 * - document_jobs is tracked pending -> processing -> completed/failed,
 * - failures are only persisted as `failed` after the final attempt; retryable
 *   failures are rethrown so the queue can retry with backoff,
 * - unique per document id so the same document can never be generated twice
 *   concurrently,
 * - never logs payload data, HTML or secrets (document id + error code only).
 */
final class GenerateDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Error codes that will never succeed on a retry.
     *
     * @var list<string>
     */
    private const NON_RETRYABLE_CODES = [
        DocumentGenerationException::CODE_TEMPLATE_NOT_FOUND,
        DocumentGenerationException::CODE_TEMPLATE_INACTIVE,
        DocumentGenerationException::CODE_VALIDATION_ERROR,
        DocumentGenerationException::CODE_RENDER_ERROR,
    ];

    public int $tries;

    public int $timeout;

    /**
     * @var int|list<int>
     */
    public int|array $backoff;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $documentId,
        ?int $tries = null,
        ?int $timeout = null,
        int|array|null $backoff = null,
    ) {
        $this->tries = max(1, $tries ?? (int) config('pdf.queue.tries', 3));
        $this->timeout = max(1, $timeout ?? (int) config('pdf.queue.timeout', 180));
        $this->backoff = $backoff ?? (int) config('pdf.queue.backoff', 15);
        $this->onQueue((string) config('pdf.queue.name', 'documents'));

        $connection = config('pdf.queue.connection');

        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    /**
     * @throws DocumentGenerationException
     * @throws ValidationException
     */
    public function handle(DocumentGenerationService $service): void
    {
        $document = GeneratedDocument::query()->withTrashed()->find($this->documentId);

        if (! $document instanceof GeneratedDocument) {
            return;
        }

        if ($document->status === DocumentStatus::Completed) {
            return;
        }

        $job = $this->latestJob($document);
        $job?->markProcessing();

        try {
            $service->processQueuedDocument($document, $job);

            if ($job instanceof DocumentJob) {
                $job->markCompleted($this->jobDurationMs($job));
            }
        } catch (DocumentGenerationException $exception) {
            if ($this->isNonRetryable($exception->getErrorCode())) {
                $this->finalizeFailure($document, $job, $exception->getErrorCode(), $exception->getMessage());

                return;
            }

            Log::warning('Document job attempt failed', [
                'document_id' => $document->getKey(),
                'error_code' => $exception->getErrorCode(),
                'attempt' => $this->attempts(),
            ]);

            throw $exception;
        } catch (ValidationException $exception) {
            unset($exception);
            $this->finalizeFailure(
                $document,
                $job,
                DocumentGenerationException::CODE_VALIDATION_ERROR,
                'داده‌های ذخیره‌شده قالب معتبر نیست.',
            );

            return;
        } catch (Throwable $exception) {
            Log::error('Document job attempt failed unexpectedly', [
                'document_id' => $document->getKey(),
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Called by the queue worker after the final failed attempt.
     */
    public function failed(?Throwable $exception): void
    {
        $document = GeneratedDocument::query()->withTrashed()->find($this->documentId);

        if (! $document instanceof GeneratedDocument) {
            return;
        }

        [$errorCode, $errorMessage] = $this->describeFailure($exception);
        $job = $this->latestJob($document);

        if ($job instanceof DocumentJob) {
            $this->markJobFailed($job, $errorCode, $errorMessage);
        }

        $document->markFailed($errorCode, $errorMessage);

        event(new DocumentGenerationFailed($document, $errorCode, $errorMessage, (int) $this->attempts()));

        Log::error('Document generation failed permanently', [
            'document_id' => $document->getKey(),
            'error_code' => $errorCode,
            'attempts' => $this->attempts(),
        ]);
    }

    private function latestJob(GeneratedDocument $document): ?DocumentJob
    {
        /** @var DocumentJob|null $job */
        $job = DocumentJob::query()
            ->where('generated_document_id', $document->getKey())
            ->latest('id')
            ->first();

        return $job;
    }

    private function finalizeFailure(
        GeneratedDocument $document,
        ?DocumentJob $job,
        string $errorCode,
        string $errorMessage,
    ): void {
        if ($job instanceof DocumentJob) {
            $this->markJobFailed($job, $errorCode, $errorMessage);
        }

        $document->markFailed($errorCode, $errorMessage);

        event(new DocumentGenerationFailed($document, $errorCode, $errorMessage, (int) $this->attempts()));

        Log::error('Document generation failed permanently', [
            'document_id' => $document->getKey(),
            'error_code' => $errorCode,
            'attempts' => $this->attempts(),
        ]);
    }

    private function markJobFailed(DocumentJob $job, string $errorCode, string $errorMessage): void
    {
        $durationMs = $this->jobDurationMs($job);
        $job->markFailed($errorCode, $errorMessage);

        if ($job->duration_ms === null && $durationMs !== null) {
            $job->forceFill(['duration_ms' => $durationMs])->save();
        }
    }

    private function jobDurationMs(DocumentJob $job): ?int
    {
        $startedAt = $job->started_at;

        if ($startedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds(now());
    }

    private function isNonRetryable(string $errorCode): bool
    {
        return in_array($errorCode, self::NON_RETRYABLE_CODES, true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function describeFailure(?Throwable $exception): array
    {
        if ($exception instanceof GotenbergException) {
            return [$exception->getErrorCode(), $exception->getMessage()];
        }

        if ($exception instanceof DocumentGenerationException) {
            return [$exception->getErrorCode(), $exception->getMessage()];
        }

        if ($exception instanceof ValidationException) {
            return [DocumentGenerationException::CODE_VALIDATION_ERROR, 'داده‌های ذخیره‌شده قالب معتبر نیست.'];
        }

        if ($exception instanceof TemplateRenderException) {
            return [
                DocumentGenerationException::CODE_RENDER_ERROR,
                'خطا در پردازش قالب سند رخ داد.',
            ];
        }

        return [
            DocumentGenerationException::CODE_RENDER_ERROR,
            'خطای غیرمنتظره در تولید سند رخ داد.',
        ];
    }
}
