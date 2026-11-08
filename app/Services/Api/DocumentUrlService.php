<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\GeneratedDocument;
use Illuminate\Support\Facades\URL;

/**
 * Generates the public links returned for a generated document.
 */
final class DocumentUrlService
{
    public function statusUrl(GeneratedDocument $document): string
    {
        return route('api.documents.status', ['ulid' => $document->ulid]);
    }

    public function signedDownloadUrl(GeneratedDocument $document): string
    {
        return URL::temporarySignedRoute(
            'api.documents.download.signed',
            now()->addMinutes($this->signedUrlTtlMinutes()),
            ['ulid' => $document->ulid],
        );
    }

    public function signedUrlTtlMinutes(): int
    {
        return max(1, (int) config('pdf.signed_url_ttl', 30));
    }
}
