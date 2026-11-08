<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TemplateOrientation;
use App\Enums\TemplatePageSize;
use App\Enums\TemplateVariableType;
use App\Http\Requests\Admin\Concerns\ValidatesTemplateMarkup;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TemplateRequest extends FormRequest
{
    use ValidatesTemplateMarkup;

    public function authorize(): bool
    {
        $template = $this->route('template');

        if ($template instanceof Template) {
            return Gate::allows('update', $template);
        }

        return Gate::allows('create', Template::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Template|null $template */
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('templates', 'slug')->ignore($template?->getKey()),
            ],
            'category' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-_]+$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'locale' => ['required', 'string', 'max:10', 'in:fa,en,ar,tr'],
            'is_active' => ['nullable', 'boolean'],

            'html' => ['required', 'string', 'max:2097152'],
            'css' => ['nullable', 'string', 'max:1048576'],
            'header_html' => ['nullable', 'string', 'max:524288'],
            'footer_html' => ['nullable', 'string', 'max:524288'],

            'page_size' => ['required', Rule::enum(TemplatePageSize::class)],
            'orientation' => ['required', Rule::enum(TemplateOrientation::class)],
            'margins' => ['nullable', 'array'],
            'margins.top' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margins.right' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margins.bottom' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margins.left' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'pdf_options' => ['nullable', 'array'],
            'pdf_options.print_background' => ['nullable', 'boolean'],
            'pdf_options.prefer_css_page_size' => ['nullable', 'boolean'],
            'pdf_options.display_header_footer' => ['nullable', 'boolean'],
            'pdf_options.scale' => ['nullable', 'numeric', 'min:0.1', 'max:2'],
            'pdf_options.custom_width' => ['nullable', 'numeric', 'min:10', 'max:2000'],
            'pdf_options.custom_height' => ['nullable', 'numeric', 'min:10', 'max:2000'],

            'version_note' => ['nullable', 'string', 'max:255'],

            'variables' => ['nullable', 'array', 'max:100'],
            'variables.*.name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                'distinct',
            ],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.type' => ['required', Rule::enum(TemplateVariableType::class)],
            'variables.*.is_required' => ['nullable', 'boolean'],
            'variables.*.default_value' => ['nullable', 'string', 'max:5000'],
            'variables.*.description' => ['nullable', 'string', 'max:1000'],
            'variables.*.validation_rules' => ['nullable', 'string', 'max:500'],
            'variables.*.min_length' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'variables.*.max_length' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'variables.*.min_value' => ['nullable', 'numeric'],
            'variables.*.max_value' => ['nullable', 'numeric'],
            'variables.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateTemplateMarkup($validator);

            $variables = $this->input('variables');

            if (! is_array($variables)) {
                return;
            }

            foreach ($variables as $index => $variable) {
                if (! is_array($variable)) {
                    continue;
                }

                $minLength = $variable['min_length'] ?? null;
                $maxLength = $variable['max_length'] ?? null;

                if ($minLength !== null && $maxLength !== null && $minLength !== '' && $maxLength !== ''
                    && (int) $minLength > (int) $maxLength) {
                    $validator->errors()->add(
                        'variables.'.$index.'.max_length',
                        'حداکثر طول نباید کمتر از حداقل طول باشد.',
                    );
                }

                $minValue = $variable['min_value'] ?? null;
                $maxValue = $variable['max_value'] ?? null;

                if ($minValue !== null && $maxValue !== null && $minValue !== '' && $maxValue !== ''
                    && (float) $minValue > (float) $maxValue) {
                    $validator->errors()->add(
                        'variables.'.$index.'.max_value',
                        'حداکثر مقدار نباید کمتر از حداقل مقدار باشد.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'اسلاگ فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
            'category.regex' => 'دسته‌بندی فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد، خط تیره و زیرخط باشد.',
            'variables.*.name.regex' => 'نام متغیر باید با حرف یا زیرخط شروع شود و فقط شامل حروف انگلیسی، اعداد و زیرخط باشد.',
            'variables.*.name.distinct' => 'نام متغیرها نباید تکراری باشد.',
        ];
    }
}
