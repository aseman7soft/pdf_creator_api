<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\GeneratedDocument;

/**
 * Fired when a document could not be generated (synchronously or after the
 * last queued attempt). Contains no payload data, only the fixed error code
 * and the controlled Persian message.
 */
final class DocumentGenerationFailed
{
    public function __construct(
        public readonly GeneratedDocument $document,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly int $attempts = 0,
    ) {}
}
