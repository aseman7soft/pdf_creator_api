<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Creation, hashing, rotation and revocation of project API keys.
 *
 * Only the HMAC hash of a key is persisted. The plain key is returned by the
 * creation/rotation methods exactly once so it can be shown to the administrator.
 */
class ApiKeyService
{
    /**
     * @return array{key: ApiKey, plain: string}
     */
    public function create(
        ApiProject $project,
        string $name,
        ?User $createdBy = null,
        ?Carbon $expiresAt = null,
        ?int $rateLimitPerMinute = null,
    ): array {
        $plain = $this->generatePlainKey();

        $key = $project->apiKeys()->create([
            'name' => $name,
            'key_prefix' => $this->prefixFor($plain),
            'key_hash' => $this->hash($plain),
            'is_active' => true,
            'expires_at' => $expiresAt,
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'created_by' => $createdBy?->getKey(),
        ]);

        return ['key' => $key, 'plain' => $plain];
    }

    /**
     * Replace a key: revoke the old one and issue a new key in the same project.
     *
     * @return array{key: ApiKey, plain: string}
     */
    public function rotate(ApiKey $key, ?User $by = null): array
    {
        $this->revoke($key);

        return $this->create(
            $key->project,
            $key->name.' (renewed)',
            $by,
            $key->expires_at,
            $key->rate_limit_per_minute,
        );
    }

    public function revoke(ApiKey $key): void
    {
        $key->forceFill([
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();
    }

    public function hash(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('api.hash_salt'));
    }

    /**
     * Resolve a plain bearer token to a usable key. Returns null for unknown,
     * revoked or expired keys, and for inactive projects.
     */
    public function authenticate(string $plain): ?ApiKey
    {
        if ($plain === '' || strlen($plain) > 200) {
            return null;
        }

        /** @var ApiKey|null $key */
        $key = ApiKey::query()
            ->with('project')
            ->where('key_hash', $this->hash($plain))
            ->first();

        if (! $key instanceof ApiKey || ! $key->isUsable()) {
            return null;
        }

        if (! $key->project instanceof ApiProject || ! $key->project->is_active) {
            return null;
        }

        return $key;
    }

    public function generatePlainKey(): string
    {
        $bytes = max(16, (int) config('api.key_bytes', 32));

        return config('api.key_prefix', 'pdfsvc').'_'.bin2hex(random_bytes($bytes));
    }

    public function prefixFor(string $plain): string
    {
        return Str::substr($plain, 0, 20);
    }
}
