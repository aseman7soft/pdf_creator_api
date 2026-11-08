<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TemplateOrientation;
use App\Enums\TemplatePageSize;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'قالب '.fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => 'template-'.Str::lower(Str::random(8)),
            'category' => 'general',
            'description' => fake()->sentence(),
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body><h1>{{ guest_name }}</h1></body></html>',
            'css' => 'body { font-family: Vazirmatn, sans-serif; }',
            'header_html' => null,
            'footer_html' => null,
            'is_active' => true,
            'locale' => 'fa',
            'version' => 1,
            'page_size' => TemplatePageSize::A4,
            'orientation' => TemplateOrientation::Portrait,
            'margins' => ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12],
            'pdf_options' => ['print_background' => true, 'scale' => 1.0],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function landscape(): static
    {
        return $this->state(fn (): array => [
            'orientation' => TemplateOrientation::Landscape,
        ]);
    }
}
