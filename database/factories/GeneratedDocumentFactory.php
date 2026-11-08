<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiProject;
use App\Models\GeneratedDocument;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GeneratedDocument>
 */
class GeneratedDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'reference' => 'REF-'.strtoupper(Str::random(8)),
            'api_project_id' => ApiProject::factory(),
            'template_id' => Template::factory(),
            'template_name' => 'قالب تست',
            'template_version' => 1,
            'generation_mode' => 'sync',
            'status' => 'pending',
            'payload' => ['guest_name' => 'Ali Rezaei'],
            'options' => ['format' => 'A4'],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'file_path' => 'documents/test-'.Str::random(10).'.pdf',
            'file_size' => 2048,
            'generated_at' => now(),
            'pdf_meta' => ['duration_ms' => 1200],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'error_code' => 'GOTENBERG_UNAVAILABLE',
            'error_message' => 'سرویس تولید PDF در دسترس نیست.',
        ]);
    }
}
