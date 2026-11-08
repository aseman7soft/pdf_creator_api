<?php

declare(strict_types=1);

namespace App\Services\Documents\Dto;

/**
 * Successful Gotenberg conversion result (raw PDF bytes + timings).
 */
final class GotenbergResult
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public readonly string $contents,
        public readonly int $durationMs,
        public readonly ?int $statusCode = null,
        public readonly array $headers = [],
    ) {}

    public function size(): int
    {
        return strlen($this->contents);
    }
}
