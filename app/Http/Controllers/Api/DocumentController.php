<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Exceptions\ApiException;
use App\Http\Requests\Api\GenerateDocumentRequest;
use App\Http\Requests\Api\QueueDocumentRequest;
use App\Http\Resources\DocumentQueueResource;
use App\Http\Resources\DocumentStatusResource;
use App\Models\ApiProject;
use App\Models\GeneratedDocument;
use App\Models\IdempotencyKey;
use App\Services\Api\ApiResponseService;
use App\Services\Api\IdempotencyService;
use App\Services\Api\TemplateAccessService;
use App\Services\Documents\DocumentGenerationService;
use App\Services\Documents\DocumentStorageService;
use App\Services\Templates\TemplateSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Document generation, status polling and downloads.
 */
class DocumentController extends ApiController
{
    public function __construct(
        private readonly DocumentGenerationService $generation,
        private readonly DocumentStorageService $storage,
        private readonly TemplateSchemaValidator $schemaValidator,
        private readonly IdempotencyService $idempotency,
        private readonly TemplateAccessService $templates,
    ) {}

    /**
     * POST /documents/generate — synchronous generation, streamed PDF response.
     */
    public function generate(GenerateDocumentRequest $request): Response
    {
        return $this->handleApiErrors($request, fn (): Response => $this->doGenerate($request));
    }

    /**
     * POST /documents/queue — accepts the work and returns 202.
     */
    public function queue(QueueDocumentRequest $request): JsonResponse
    {
        return $this->handleApiErrors($request, fn (): JsonResponse => $this->doQueue($request));
    }

    /**
     * GET /documents/{ulid}/status
     */
    public function status(Request $request, string $ulid): JsonResponse
    {
        return $this->handleApiErrors($request, function () use ($request, $ulid): JsonResponse {
            $document = $this->findForProject($request, $ulid);

            return ApiResponseService::success(
                (new DocumentStatusResource($document))->resolve($request),
            );
        });
    }

    /**
     * GET /documents/{ulid}/download — Bearer authenticated download.
     */
    public function download(Request $request, string $ulid): Response
    {
        return $this->handleApiErrors($request, function () use ($request, $ulid): Response {
            $document = $this->findForProject($request, $ulid);

            return $this->streamDocument($document);
        });
    }

    /**
     * GET /documents/{ulid}/download/signed — temporary signed URL, no Bearer.
     */
    public function signedDownload(Request $request, string $ulid): Response
    {
        return $this->handleApiErrors($request, function () use ($request, $ulid): Response {
            /** @var GeneratedDocument|null $document */
            $document = GeneratedDocument::query()
                ->with('template')
                ->where('ulid', $ulid)
                ->first();

            if (! $document instanceof GeneratedDocument) {
                throw ApiException::notFound('سند مورد نظر یافت نشد.', 'DOCUMENT_NOT_FOUND');
            }

            $this->attribute($request, 'api_generated_document_id', $document->getKey());
            $this->attribute($request, 'api_template_id', $document->template_id);

            if ($document->project instanceof ApiProject) {
                $this->attribute($request, 'api_project', $document->project);
            }

            return $this->streamDocument($document);
        });
    }

    private function doGenerate(GenerateDocumentRequest $request): Response
    {
        $project = $this->project($request);
        $apiKey = $this->apiKey($request);
        $template = $this->templates->findForProject($project, $request->templateSlug());
        $this->attribute($request, 'api_template_id', $template->getKey());

        $data = $this->schemaValidator->validate($template, $request->templateData());
        $options = $request->generationOptions();
        $reference = $request->reference();

        $idempotencyKey = $this->idempotency->normalizeKey($request->idempotencyKey());
        $hash = $idempotencyKey === null ? null : $this->idempotency->requestHash(
            'documents.generate',
            $this->fingerprintPayload($request, $reference, $options),
        );

        if ($idempotencyKey !== null && $hash !== null) {
            $record = $this->idempotency->lookup($project, $idempotencyKey, $hash);

            if ($record instanceof IdempotencyKey) {
                return $this->replaySync($request, $record);
            }
        }

        $startedAt = microtime(true);

        $document = $this->generation->generateSync(
            $template,
            $data,
            $options,
            $project,
            $apiKey,
            $reference,
        );

        $generationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->attribute($request, 'api_generated_document_id', $document->getKey());

        if ($idempotencyKey !== null && $hash !== null) {
            $this->idempotency->store($project, $idempotencyKey, $hash, 200, $document);
        }

        return $this->streamDocument($document, $generationMs);
    }

    private function doQueue(QueueDocumentRequest $request): JsonResponse
    {
        $project = $this->project($request);
        $apiKey = $this->apiKey($request);
        $template = $this->templates->findForProject($project, $request->templateSlug());
        $this->attribute($request, 'api_template_id', $template->getKey());

        $data = $this->schemaValidator->validate($template, $request->templateData());
        $options = $request->generationOptions();
        $reference = $request->reference();

        $idempotencyKey = $this->idempotency->normalizeKey($request->idempotencyKey());
        $hash = $idempotencyKey === null ? null : $this->idempotency->requestHash(
            'documents.queue',
            $this->fingerprintPayload($request, $reference, $options),
        );

        if ($idempotencyKey !== null && $hash !== null) {
            $record = $this->idempotency->lookup($project, $idempotencyKey, $hash);

            if ($record instanceof IdempotencyKey) {
                return $this->replayQueue($request, $record);
            }
        }

        $document = $this->generation->queue(
            $template,
            $data,
            $options,
            $project,
            $apiKey,
            $reference,
        );

        $this->attribute($request, 'api_generated_document_id', $document->getKey());

        if ($idempotencyKey !== null && $hash !== null) {
            $this->idempotency->store($project, $idempotencyKey, $hash, 202, $document);
        }

        return ApiResponseService::success(
            (new DocumentQueueResource($document))->resolve($request),
            null,
            202,
        );
    }

    private function findForProject(Request $request, string $ulid): GeneratedDocument
    {
        $project = $this->project($request);

        /** @var GeneratedDocument|null $document */
        $document = GeneratedDocument::query()
            ->with('template')
            ->where('ulid', $ulid)
            ->where('api_project_id', $project->getKey())
            ->first();

        if (! $document instanceof GeneratedDocument) {
            throw ApiException::notFound('سند مورد نظر یافت نشد.', 'DOCUMENT_NOT_FOUND');
        }

        $this->attribute($request, 'api_generated_document_id', $document->getKey());
        $this->attribute($request, 'api_template_id', $document->template_id);

        return $document;
    }

    /**
     * Replay a stored synchronous generation: stream the already generated PDF.
     */
    private function replaySync(GenerateDocumentRequest $request, IdempotencyKey $record): Response
    {
        /** @var GeneratedDocument|null $document */
        $document = $record->document;

        if (! $document instanceof GeneratedDocument) {
            throw ApiException::conflict(
                'IDEMPOTENCY_REPLAY_UNAVAILABLE',
                'نتیجه درخواست قبلی قابل بازیابی نیست؛ لطفاً از کلید Idempotency جدیدی استفاده کنید.',
            );
        }

        $this->attribute($request, 'api_generated_document_id', $document->getKey());
        $this->attribute($request, 'api_template_id', $document->template_id);

        if ($document->status === DocumentStatus::Failed) {
            throw new ApiException(
                $document->error_code !== null && $document->error_code !== '' ? $document->error_code : 'GENERATION_FAILED',
                'تولید سند با همین کلید Idempotency قبلاً ناموفق بوده است.',
                is_int($record->response_status) && $record->response_status >= 400 ? $record->response_status : 503,
            );
        }

        if (! $document->canDownload()) {
            throw ApiException::conflict('DOCUMENT_NOT_READY', 'سند درخواست قبلی هنوز آماده دانلود نیست.');
        }

        $meta = is_array($document->pdf_meta) ? $document->pdf_meta : [];

        return $this->streamDocument(
            $document,
            isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : null,
            idempotentReplay: true,
        );
    }

    /**
     * Replay a queued generation: return the same document id with its current
     * status so the client can keep polling.
     */
    private function replayQueue(QueueDocumentRequest $request, IdempotencyKey $record): JsonResponse
    {
        /** @var GeneratedDocument|null $document */
        $document = $record->document;

        if (! $document instanceof GeneratedDocument) {
            throw ApiException::conflict(
                'IDEMPOTENCY_REPLAY_UNAVAILABLE',
                'نتیجه درخواست قبلی قابل بازیابی نیست؛ لطفاً از کلید Idempotency جدیدی استفاده کنید.',
            );
        }

        $this->attribute($request, 'api_generated_document_id', $document->getKey());
        $this->attribute($request, 'api_template_id', $document->template_id);

        return ApiResponseService::success(
            (new DocumentQueueResource($document))->resolve($request),
            null,
            202,
            ['X-Idempotent-Replay' => 'true'],
        );
    }

    /**
     * Stream the stored PDF with the contract headers.
     */
    private function streamDocument(
        GeneratedDocument $document,
        ?int $generationMs = null,
        bool $idempotentReplay = false,
    ): Response {
        if ($document->isExpired()) {
            throw ApiException::gone('DOCUMENT_EXPIRED', 'اعتبار این سند به پایان رسیده است.');
        }

        if ($document->status === DocumentStatus::Failed) {
            throw ApiException::conflict(
                'DOCUMENT_NOT_READY',
                'تولید این سند ناموفق بوده و فایل قابل دانلودی وجود ندارد.',
            );
        }

        if (! $document->canDownload()) {
            throw ApiException::conflict('DOCUMENT_NOT_READY', 'سند هنوز آماده دانلود نیست.');
        }

        try {
            $contents = $this->storage->read($document);
        } catch (Throwable $exception) {
            throw new ApiException(
                'STORAGE_ERROR',
                'فایل سند یافت نشد یا قابل خواندن نیست.',
                500,
                [],
                [],
                $exception,
            );
        }

        if (! is_string($contents) || $contents === '') {
            throw new ApiException('STORAGE_ERROR', 'فایل سند خالی یا نامعتبر است.', 500);
        }

        $meta = is_array($document->pdf_meta) ? $document->pdf_meta : [];
        $durationMs = $generationMs ?? (isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : 0);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($contents),
            'X-Document-Id' => (string) $document->ulid,
            'X-Generation-Time-Ms' => (string) max(0, $durationMs),
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($idempotentReplay) {
            $headers['X-Idempotent-Replay'] = 'true';
        }

        $fileName = $this->fileName($document);

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $fileName,
            $headers,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function fingerprintPayload(GenerateDocumentRequest $request, ?string $reference, array $options): array
    {
        return [
            'template' => $request->templateSlug(),
            'reference' => $reference,
            'data' => $request->templateData(),
            'options' => $options,
        ];
    }

    private function fileName(GeneratedDocument $document): string
    {
        $base = $document->reference ?: (string) $document->ulid;
        $base = (string) preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $base);
        $base = trim($base, '-_');

        if ($base === '') {
            $base = 'document';
        }

        return Str::limit($base, 80, '').'.pdf';
    }
}
