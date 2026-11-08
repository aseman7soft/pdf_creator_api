<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $path = 'assets/images/'.Str::random(12).'.png';

        return [
            'handle' => 'asset-'.Str::lower(Str::random(8)),
            'name' => 'فایل '.fake()->word(),
            'type' => AssetType::Image,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => 1024,
            'disk' => config('pdf.disk', 'local'),
            'path' => $path,
            'checksum' => hash('sha256', $path),
            'metadata' => null,
            'description' => null,
        ];
    }

    public function font(): static
    {
        return $this->state(fn (): array => [
            'type' => AssetType::Font,
            'mime_type' => 'font/woff2',
            'extension' => 'woff2',
            'path' => 'assets/fonts/'.Str::random(12).'.woff2',
        ]);
    }
}
