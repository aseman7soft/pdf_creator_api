<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Enums\TemplateVariableType;
use App\Models\Template;
use App\Models\TemplateVariable;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Applies template variable defaults, casts values to their declared types and
 * validates them with the rules contributed by TemplateVariable::validationRules().
 *
 * Errors are thrown as Illuminate\Validation\ValidationException with keys in
 * the `data.<variable>` form so the API layer can map them to a standard 422
 * response without knowing template internals.
 */
final class TemplateSchemaValidator
{
    /**
     * Build the Laravel validation rule map for a template's data payload.
     *
     * @return array<string, list<string>>
     */
    public function rules(Template $template): array
    {
        $template->loadMissing('variables');
        $rules = [];

        foreach ($template->variables as $variable) {
            $variableRules = $variable->validationRules();
            $variableRules = array_values(array_filter(
                $variableRules,
                static fn (string $rule): bool => ! in_array($rule, ['required', 'nullable'], true),
            ));

            array_unshift($variableRules, $variable->is_required ? 'required' : 'nullable');

            $rules['data.'.$variable->name] = $variableRules;
        }

        return $rules;
    }

    /**
     * Validate and normalise incoming data for a template.
     *
     * Unknown keys are preserved so templates may intentionally use ad-hoc
     * variables (e.g. a nested object item); declared variables are always
     * present in the result when they have a default.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Template $template, array $data): array
    {
        $template->loadMissing('variables');

        $prepared = $data;

        foreach ($template->variables as $variable) {
            $key = $variable->name;
            $hasValue = array_key_exists($key, $prepared) && $prepared[$key] !== null;

            if (! $hasValue) {
                $default = $variable->defaultCasted();

                if ($default !== null) {
                    $prepared[$key] = $default;
                }

                continue;
            }

            $prepared[$key] = $this->castValue($variable, $prepared[$key]);
        }

        $rules = $this->rules($template);

        if ($rules === []) {
            return $prepared;
        }

        $attributes = [];

        foreach ($template->variables as $variable) {
            $attributes['data.'.$variable->name] = $variable->label ?: $variable->name;
        }

        Validator::make(['data' => $prepared], $rules, [], $attributes)->validate();

        return $prepared;
    }

    private function castValue(TemplateVariable $variable, mixed $value): mixed
    {
        return match ($variable->type) {
            TemplateVariableType::Integer => $this->castInteger($value),
            TemplateVariableType::Decimal => $this->castDecimal($value),
            TemplateVariableType::Boolean => $this->castBoolean($value),
            TemplateVariableType::Date => $this->castDate($value, 'Y-m-d'),
            TemplateVariableType::DateTime => $this->castDate($value, 'Y-m-d H:i:s'),
            TemplateVariableType::Array => $this->castArray($value),
            TemplateVariableType::Object => $this->castObject($value),
            default => $this->castString($value),
        };
    }

    private function castInteger(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $value;
    }

    private function castDecimal(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return $value;
    }

    private function castBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            if (in_array($normalised, ['true', 'false'], true)) {
                return $normalised === 'true';
            }
        }

        return $value;
    }

    private function castDate(mixed $value, string $format): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    private function castArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode((string) json_encode($value), true);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    private function castObject(mixed $value): mixed
    {
        if (is_object($value)) {
            return json_decode((string) json_encode($value), true);
        }

        return $this->castArray($value);
    }

    private function castString(mixed $value): mixed
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $value;
    }
}
