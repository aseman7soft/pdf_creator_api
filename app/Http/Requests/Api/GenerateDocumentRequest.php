<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TemplateOrientation;
use App\Enums\TemplatePageSize;
use Closure;

/**
 * Shared validation for the synchronous and queued generation endpoints.
 *
 * Template existence/access and template-specific variable validation are
 * resolved by the controller/engine so the API can return the correct machine
 * readable error codes (404 / 403 / 422 with data.<name> details).
 */
class GenerateDocumentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:120'],
            'data' => [
                // `present` (not `required`) so an empty object can still be
                // validated by the template schema validator, which reports
                // precise `data.<variable>` errors for missing required values.
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && $value !== [] && array_is_list($value)) {
                        $fail('فیلد data باید یک شیء (object) با کلیدهای نام متغیرها باشد.');
                    }
                },
            ],
            'options' => ['nullable', 'array'],
            'options.format' => [
                'nullable',
                'string',
                'in:'.implode(',', array_map(
                    static fn (TemplatePageSize $case): string => $case->value,
                    TemplatePageSize::cases(),
                )),
            ],
            'options.orientation' => [
                'nullable',
                'string',
                'in:'.implode(',', array_map(
                    static fn (TemplateOrientation $case): string => $case->value,
                    TemplateOrientation::cases(),
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template.required' => 'شناسه (slug) قالب الزامی است.',
            'template.max' => 'شناسه قالب بیش از حد طولانی است.',
            'reference.max' => 'مرجع سند حداکثر می‌تواند ۱۲۰ کاراکتر باشد.',
            'data.required' => 'داده‌های قالب (data) الزامی است.',
            'data.present' => 'داده‌های قالب (data) الزامی است.',
            'data.array' => 'داده‌های قالب باید به صورت شیء ارسال شود.',
            'options.array' => 'گزینه‌های تولید باید به صورت شیء ارسال شوند.',
            'options.format.in' => 'اندازه صفحه باید یکی از مقادیر A4، A3، A5، Letter، Legal یا Custom باشد.',
            'options.orientation.in' => 'جهت صفحه باید portrait یا landscape باشد.',
        ];
    }

    public function templateSlug(): string
    {
        return trim((string) $this->input('template'));
    }

    /**
     * @return array<string, mixed>
     */
    public function templateData(): array
    {
        $data = $this->input('data');

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function generationOptions(): array
    {
        $options = $this->input('options');

        if (! is_array($options)) {
            return [];
        }

        // Never let unknown client supplied options reach the renderer unfiltered.
        $allowed = ['format', 'orientation'];

        return array_filter(
            array_intersect_key($options, array_flip($allowed)),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    public function reference(): ?string
    {
        $reference = $this->input('reference');

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key') ?? $this->header('X-Idempotency-Key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }
}
