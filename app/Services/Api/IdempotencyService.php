<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Exceptions\ApiException;
use App\Models\ApiProject;
use App\Models\GeneratedDocument;
use App\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Idempotency keys for POST /documents/generate and POST /documents/queue.
 *
 * A key is scoped to one API project. Replaying the same key with the same
 * payload returns the previously stored result; reusing it with a different
 * payload is rejected (422). Expired keys are cleaned up lazily.
 */
final class IdempotencyService
{
    public const HEADER = 'Idempotency-Key';

    private const MAX_KEY_LENGTH = 120;

    /**
     * Stable request fingerprint. Keys are sorted recursively so JSON key order
     * does not matter. The endpoint name is part of the hash so a key cannot be
     * silently reused across generate/queue.
     *
     * @param  array<string, mixed>  $payload
     */
    public function requestHash(string $endpoint, array $payload): string
    {
        $canonical = json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        return hash('sha256', $endpoint.'|'.($canonical === false ? '' : $canonical));
    }

    public function normalizeKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim($key);

        if ($key === '') {
            return null;
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw ApiException::unprocessable(
                'IDEMPOTENCY_KEY_INVALID',
                'کلید Idempotency بیش از حد طولانی است.',
            );
        }

        if (preg_match('/^[A-Za-z0-9._~:\-]+$/', $key) !== 1) {
            throw ApiException::unprocessable(
                'IDEMPOTENCY_KEY_INVALID',
                'کلید Idempotency فقط می‌تواند شامل حروف، رقم و کاراکترهای . _ ~ : - باشد.',
            );
        }

        return $key;
    }

    /**
     * Return the stored record for this key, or null when the request is new.
     *
     * @throws ApiException when the same key was used with another payload
     */
    public function lookup(ApiProject $project, ?string $key, string $hash): ?IdempotencyKey
    {
        if ($key === null) {
            return null;
        }

        $this->cleanupExpired($project);

        /** @var IdempotencyKey|null $record */
        $record = IdempotencyKey::query()
            ->where('api_project_id', $project->getKey())
            ->where('key', $key)
            ->first();

        if (! $record instanceof IdempotencyKey) {
            return null;
        }

        if ($record->isExpired()) {
            $record->delete();

            return null;
        }

        if (! hash_equals($record->request_hash, $hash)) {
            throw ApiException::unprocessable(
                'IDEMPOTENCY_KEY_REUSED',
                'این کلید Idempotency قبلاً با بدنه درخواست متفاوتی استفاده شده است.',
            );
        }

        return $record->loadMissing('document');
    }

    public function store(
        ApiProject $project,
        string $key,
        string $hash,
        int $responseStatus,
        ?GeneratedDocument $document = null,
    ): IdempotencyKey {
        $expiresAt = now()->addHours(max(1, (int) config('api.idempotency_ttl_hours', 24)));

        try {
            /** @var IdempotencyKey $record */
            $record = IdempotencyKey::query()->create([
                'api_project_id' => $project->getKey(),
                'key' => $key,
                'request_hash' => $hash,
                'response_status' => $responseStatus,
                'generated_document_id' => $document?->getKey(),
                'expires_at' => $expiresAt,
            ]);

            return $record;
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race. Prefer its row.
            $record = IdempotencyKey::query()
                ->where('api_project_id', $project->getKey())
                ->where('key', $key)
                ->first();

            if ($record instanceof IdempotencyKey) {
                if (! hash_equals($record->request_hash, $hash)) {
                    throw ApiException::unprocessable(
                        'IDEMPOTENCY_KEY_REUSED',
                        'این کلید Idempotency قبلاً با بدنه درخواست متفاوتی استفاده شده است.',
                    );
                }

                return $record;
            }

            throw ApiException::unprocessable(
                'IDEMPOTENCY_KEY_REUSED',
                'این کلید Idempotency قبلاً استفاده شده است.',
            );
        }
    }

    public function cleanupExpired(ApiProject $project): void
    {
        IdempotencyKey::query()
            ->where('api_project_id', $project->getKey())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
