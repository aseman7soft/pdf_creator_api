<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use App\Enums\JobStatus;
use App\Events\DocumentGenerated;
use App\Events\DocumentGenerationFailed;
use App\Exceptions\DocumentGenerationException;
use App\Jobs\GenerateDocumentJob;
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\User;
use App\Services\Documents\Dto\RenderedDocument;
use App\Services\Gotenberg\GotenbergService;
use App\Services\Templates\TemplateRenderService;
use App\Services\Templates\TemplateSchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Orchestrates the full document-generation pipeline:
 * validation -> render -> Gotenberg -> private storage -> status tracking.
 *
 * Every controlled failure carries one of the fixed error codes from
 * docs/CONTRACTS.md §4 and never leaks HTML, payload data, secrets or stack
 * traces to API consumers.
 */
final class DocumentGenerationService
{
    public function __construct(
        private readonly TemplateRenderService $renderer,
        private readonly TemplateSchemaValidator $validator,
        private readonly GotenbergService $gotenberg,
        private readonly DocumentStorageService $storage,
    ) {}

    /**
     * Synchronous generation used by POST /documents/generate.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function generateSync(
        Template $template,
        array $data,
        array $options = [],
        ?ApiProject $project = null,
        ?ApiKey $apiKey = null,
        ?string $reference = null,
        ?User $user = null,
    ): GeneratedDocument {
        unset($user);

        $this->assertTemplateActive($template);
        $validated = $this->validator->validate($template, $data);
        $document = $this->createDocument(
            $template,
            $validated,
            $options,
            $project,
            $apiKey,
            $reference,
            GenerationMode::Sync,
        );

        try {
            $document->markProcessing();
            [$path, $size, $meta] = $this->renderAndStore($template, $validated, $options, $document);
            $document->markCompleted($path, $size, $meta);

            event(new DocumentGenerated($document, (int) ($meta['duration_ms'] ?? 0)));

            return $document->refresh();
        } catch (ValidationException $exception) {
            $this->failDocument($document, DocumentGenerationException::CODE_VALIDATION_ERROR, 'داده‌های ورودی با متغیرهای قالب سازگار نیست.', $exception);

            throw $exception;
        } catch (DocumentGenerationException $exception) {
            $this->failDocument($document, $exception->getErrorCode(), $exception->getMessage(), $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->failDocument($document, DocumentGenerationException::CODE_RENDER_ERROR, 'خطای غیرمنتظره در تولید سند رخ داد.', $exception);

            throw DocumentGenerationException::renderError('خطای غیرمنتظره در تولید سند رخ داد.', $exception);
        }
    }

    /**
     * Queue a document for asynchronous generation used by POST /documents/queue.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function queue(
        Template $template,
        array $data,
        array $options = [],
        ?ApiProject $project = null,
        ?ApiKey $apiKey = null,
        ?string $reference = null,
    ): GeneratedDocument {
        $this->assertTemplateActive($template);
        $validated = $this->validator->validate($template, $data);

        $document = DB::transaction(function () use ($template, $validated, $options, $project, $apiKey, $reference): GeneratedDocument {
            $document = $this->createDocument(
                $template,
                $validated,
                $options,
                $project,
                $apiKey,
                $reference,
                GenerationMode::Async,
            );
            $this->createJob($document);

            return $document;
        });

        $this->dispatch($document);

        return $document->refresh();
    }

    /**
     * Re-queue a failed document (admin panel action).
     *
     * @throws InvalidArgumentException when the document is not failed
     * @throws DocumentGenerationException when the template is missing/inactive
     */
    public function retry(GeneratedDocument $document): void
    {
        if ($document->status !== DocumentStatus::Failed) {
            throw new InvalidArgumentException('تنها اسناد ناموفق قابل تلاش مجدد هستند.');
        }

        $template = $document->template;

        if (! $template instanceof Template) {
            throw DocumentGenerationException::templateNotFound();
        }

        $this->assertTemplateActive($template);

        DB::transaction(function () use ($document): void {
            $document->forceFill([
                'status' => DocumentStatus::Pending,
                'error_code' => null,
                'error_message' => null,
                'generated_at' => null,
            ])->save();

            $this->createJob($document);
        });

        $this->dispatch($document);
    }

    /**
     * Render and convert to PDF without persisting anything (panel preview).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     * @return array{contents: string, duration_ms: int, size: int}
     */
    public function preview(Template $template, array $data, array $options = []): array
    {
        $validated = $this->validator->validate($template, $data);
        $startedAt = microtime(true);
        $rendered = $this->renderer->render($template, $validated, $options);
        $result = $this->gotenberg->generate($rendered, $rendered->pdfOptions);

        return [
            'contents' => $result->contents,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'size' => strlen($result->contents),
        ];
    }

    /**
     * Render only the HTML document (panel HTML preview).
     *
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(Template $template, array $data): RenderedDocument
    {
        $validated = $this->validator->validate($template, $data);

        return $this->renderer->render($template, $validated);
    }

    /**
     * Process a document that was queued by the API (called by the job).
     *
     * @throws DocumentGenerationException
     * @throws ValidationException
     */
    public function processQueuedDocument(GeneratedDocument $document, ?DocumentJob $job = null): void
    {
        unset($job);

        $template = $document->template;

        if (! $template instanceof Template) {
            throw DocumentGenerationException::templateNotFound();
        }

        $this->assertTemplateActive($template);

        $payload = is_array($document->payload) ? $document->payload : [];
        $validated = $this->validator->validate($template, $payload);
        $options = is_array($document->options) ? $document->options : [];

        $document->markProcessing();
        [$path, $size, $meta] = $this->renderAndStore($template, $validated, $options, $document);
        $document->markCompleted($path, $size, $meta);

        event(new DocumentGenerated($document, (int) ($meta['duration_ms'] ?? 0)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     * @return array{0: string, 1: int, 2: array<string, mixed>}
     */
    private function renderAndStore(Template $template, array $data, array $options, GeneratedDocument $document): array
    {
        $startedAt = microtime(true);

        $rendered = $this->renderer->render($template, $data, $options);
        $result = $this->gotenberg->generate($rendered, $rendered->pdfOptions);
        $path = $this->storage->store($document, $result->contents);

        $size = strlen($result->contents);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [$path, $size, [
            'duration_ms' => $durationMs,
            'size' => $size,
            'gotenberg_ms' => $result->durationMs,
            'asset_count' => count($rendered->assets),
            'template_version' => (int) $template->version,
            'format' => $rendered->pdfOptions->format,
            'orientation' => $rendered->pdfOptions->landscape ? 'landscape' : 'portrait',
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function createDocument(
        Template $template,
        array $data,
        array $options,
        ?ApiProject $project,
        ?ApiKey $apiKey,
        ?string $reference,
        GenerationMode $mode,
    ): GeneratedDocument {
        return GeneratedDocument::query()->create([
            'reference' => $reference,
            'api_project_id' => $project?->getKey(),
            'api_key_id' => $apiKey?->getKey(),
            'template_id' => $template->getKey(),
            'template_name' => $template->name,
            'template_version' => (int) $template->version,
            'generation_mode' => $mode,
            'status' => DocumentStatus::Pending,
            'payload' => $data,
            'options' => $options,
            'expires_at' => now()->addDays($this->retentionDays()),
        ]);
    }

    private function createJob(GeneratedDocument $document): DocumentJob
    {
        return DocumentJob::query()->create([
            'generated_document_id' => $document->getKey(),
            'queue' => $this->queueName(),
            'status' => JobStatus::Pending,
            'attempts' => 0,
            'max_attempts' => max(1, (int) config('pdf.queue.tries', 3)),
            'available_at' => now(),
        ]);
    }

    private function dispatch(GeneratedDocument $document): void
    {
        $job = new GenerateDocumentJob((int) $document->getKey());

        $connection = config('pdf.queue.connection');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        $job->onQueue($this->queueName());

        dispatch($job);
    }

    private function assertTemplateActive(Template $template): void
    {
        if (! $template->is_active) {
            throw DocumentGenerationException::templateInactive();
        }
    }

    private function failDocument(
        GeneratedDocument $document,
        string $errorCode,
        string $errorMessage,
        ?Throwable $exception = null,
    ): void {
        try {
            $document->markFailed($errorCode, $errorMessage);
        } catch (Throwable $markException) {
            Log::error('Document failure status could not be persisted', [
                'document_id' => $document->getKey(),
                'error_code' => $errorCode,
                'exception' => $markException::class,
            ]);
        }

        event(new DocumentGenerationFailed($document, $errorCode, $errorMessage, 0));

        Log::error('Document generation failed', [
            'document_id' => $document->getKey(),
            'error_code' => $errorCode,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }

    private function queueName(): string
    {
        $name = (string) config('pdf.queue.name', 'documents');

        return $name !== '' ? $name : 'documents';
    }

    private function retentionDays(): int
    {
        $days = setting('documents.retention_days', (int) config('pdf.retention_days', 30));

        return max(1, (int) $days);
    }
}
