<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\GeneratedDocument;

/**
 * Fired after a document PDF has been generated and persisted successfully.
 */
final class DocumentGenerated
{
    public function __construct(
        public readonly GeneratedDocument $document,
        public readonly int $durationMs = 0,
    ) {}
}
