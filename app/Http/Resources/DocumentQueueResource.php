<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GeneratedDocument;
use App\Services\Api\DocumentUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 202 queue acknowledgement payload.
 *
 * @mixin GeneratedDocument
 */
class DocumentQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GeneratedDocument $document */
        $document = $this->resource;
        $urls = app(DocumentUrlService::class);

        return [
            'id' => $document->ulid,
            'status' => $document->status->value,
            'status_url' => $urls->statusUrl($document),
            'download_url' => $urls->signedDownloadUrl($document),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }
}
