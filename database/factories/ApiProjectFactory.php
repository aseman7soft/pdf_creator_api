<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiProject>
 */
class ApiProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'proj_'.Str::lower(Str::random(10)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'rate_limit_per_minute' => null,
            'monthly_quota' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
