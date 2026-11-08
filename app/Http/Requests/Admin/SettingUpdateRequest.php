<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Setting::class);
    }

    /**
     * Build rules from the registered settings definitions so adding a new
     * setting to SettingsService automatically gets validated here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $rules = [];

        foreach ($settings->defaults() as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'integer', 'int' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'float', 'decimal' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
                'boolean', 'bool' => ['nullable', 'boolean'],
                'json', 'array' => ['nullable', 'json'],
                default => ['nullable', 'string', 'max:1000'],
            };
        }

        // Tighter rules for well-known keys.
        $rules['general.brand_color'] = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules['general.support_email'] = ['nullable', 'email', 'max:190'];
        $rules['documents.default_format'] = ['nullable', 'string', 'in:A4,A3,A5,Letter,Legal,Custom'];
        $rules['gotenberg.url'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'general.brand_color.regex' => 'Ø±Ù†Ú¯ Ø¨Ø±Ù†Ø¯ Ø¨Ø§ÛŒØ¯ Ø¨Ù‡ ØµÙˆØ±Øª ÛŒÚ© Ú©Ø¯ HEX Ø´Ø´â€ŒØ±Ù‚Ù…ÛŒ Ù…Ø§Ù†Ù†Ø¯ â€Ž#0d6efd Ø¨Ø§Ø´Ø¯.',
            'documents.default_format.in' => 'Ø§Ù†Ø¯Ø§Ø²Ù‡ ØµÙØ­Ù‡ Ù¾ÛŒØ´â€ŒÙØ±Ø¶ Ù…Ø¹ØªØ¨Ø± Ù†ÛŒØ³Øª.',
        ];
    }
}
