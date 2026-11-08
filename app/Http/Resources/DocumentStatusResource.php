<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DocumentStatus;
use App\Models\GeneratedDocument;
use App\Services\Api\DocumentUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Status payload for GET /documents/{ulid}/status.
 *
 * @mixin GeneratedDocument
 */
class DocumentStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GeneratedDocument $document */
        $document = $this->resource;
        $urls = app(DocumentUrlService::class);

        $isFailed = $document->status === DocumentStatus::Failed;
        $template = $document->template;

        return [
            'id' => $document->ulid,
            'reference' => $document->reference,
            'status' => $document->status->value,
            'template' => [
                'slug' => $template?->slug,
                'name' => $document->template_name ?? $template?->name,
                'version' => $document->template_version ?? $template?->version,
            ],
            'created_at' => $document->created_at?->toIso8601String(),
            'completed_at' => $document->generated_at?->toIso8601String(),
            'expires_at' => $document->expires_at?->toIso8601String(),
            'file_size' => $document->file_size,
            'download_url' => $document->canDownload() ? $urls->signedDownloadUrl($document) : null,
            'error' => $isFailed ? [
                'code' => $document->error_code,
                'message' => $document->error_message,
            ] : null,
        ];
    }
}
