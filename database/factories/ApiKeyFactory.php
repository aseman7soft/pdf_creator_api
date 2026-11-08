<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\ApiProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'pdfsvc_'.bin2hex(random_bytes(16));

        return [
            'api_project_id' => ApiProject::factory(),
            'name' => 'کلید '.fake()->word(),
            'key_prefix' => Str::substr($plain, 0, 20),
            'key_hash' => hash_hmac('sha256', $plain, (string) config('api.hash_salt')),
            'is_active' => true,
            'expires_at' => null,
            'rate_limit_per_minute' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
