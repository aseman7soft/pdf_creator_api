<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TemplateVariableType;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateVariable>
 */
class TemplateVariableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'name' => 'var_'.Str::lower(Str::random(6)),
            'label' => 'متغیر '.fake()->word(),
            'type' => TemplateVariableType::String,
            'is_required' => false,
            'default_value' => null,
            'description' => null,
            'validation_rules' => null,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => [
            'is_required' => true,
        ]);
    }
}
