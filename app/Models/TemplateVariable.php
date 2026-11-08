<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateVariableType;
use Database\Factories\TemplateVariableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVariable extends Model
{
    /** @use HasFactory<TemplateVariableFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'label',
        'type',
        'is_required',
        'default_value',
        'description',
        'validation_rules',
        'min_length',
        'max_length',
        'min_value',
        'max_value',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TemplateVariableType::class,
            'is_required' => 'boolean',
            'min_length' => 'integer',
            'max_length' => 'integer',
            'min_value' => 'float',
            'max_value' => 'float',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function schemaEntry(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->is_required,
            'default' => $this->defaultCasted(),
            'description' => $this->description,
            'validation' => [
                'rules' => $this->validation_rules,
                'min_length' => $this->min_length,
                'max_length' => $this->max_length,
                'min_value' => $this->min_value,
                'max_value' => $this->max_value,
            ],
        ];
    }

    public function defaultCasted(): mixed
    {
        if ($this->default_value === null || $this->default_value === '') {
            return null;
        }

        return match ($this->type) {
            TemplateVariableType::Integer => (int) $this->default_value,
            TemplateVariableType::Decimal => (float) $this->default_value,
            TemplateVariableType::Boolean => filter_var($this->default_value, FILTER_VALIDATE_BOOL),
            TemplateVariableType::Array, TemplateVariableType::Object => json_decode($this->default_value, true),
            default => (string) $this->default_value,
        };
    }

    /**
     * Build the Laravel validation rules contributed by this variable.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $rules = [];

        $rules[] = match ($this->type) {
            TemplateVariableType::Integer => 'integer',
            TemplateVariableType::Decimal => 'numeric',
            TemplateVariableType::Boolean => 'boolean',
            TemplateVariableType::Date => 'date',
            TemplateVariableType::DateTime => 'date',
            TemplateVariableType::Image => 'string',
            TemplateVariableType::Url => 'url',
            TemplateVariableType::Array => 'array',
            TemplateVariableType::Object => 'array',
            TemplateVariableType::Text => 'string',
            TemplateVariableType::String => 'string',
        };

        if ($this->min_length !== null) {
            $rules[] = 'min:'.$this->min_length;
        }

        if ($this->max_length !== null) {
            $rules[] = 'max:'.$this->max_length;
        }

        if ($this->min_value !== null && in_array($this->type, [TemplateVariableType::Integer, TemplateVariableType::Decimal], true)) {
            $rules[] = 'gte:'.$this->min_value;
        }

        if ($this->max_value !== null && in_array($this->type, [TemplateVariableType::Integer, TemplateVariableType::Decimal], true)) {
            $rules[] = 'lte:'.$this->max_value;
        }

        if (is_string($this->validation_rules) && trim($this->validation_rules) !== '') {
            foreach (explode('|', $this->validation_rules) as $rule) {
                $rule = trim($rule);

                if ($rule !== '') {
                    $rules[] = $rule;
                }
            }
        }

        return array_values(array_unique($rules));
    }
}
