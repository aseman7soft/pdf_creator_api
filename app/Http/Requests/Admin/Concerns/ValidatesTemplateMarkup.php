<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Validator;

/**
 * Rejects template markup that could execute PHP or Blade directives that are
 * not supported by the safe template engine.
 */
trait ValidatesTemplateMarkup
{
    /**
     * Fields scanned for forbidden markup.
     *
     * @var list<string>
     */
    protected array $markupFields = ['html', 'css', 'header_html', 'footer_html'];

    /**
     * @var list<string>
     */
    protected array $forbiddenPatterns = [
        '/<\?/',
        '/@php\b/i',
        '/@endphp\b/i',
        '/@include\b/i',
        '/@includeIf\b/i',
        '/@extends\b/i',
        '/@inject\b/i',
        '/@use\b/i',
        '/@eval\b/i',
        '/@section\b/i',
        '/@yield\b/i',
        '/@stack\b/i',
        '/@push\b/i',
        '/@once\b/i',
        '/@verbatim\b/i',
        '/@component\b/i',
        '/@class\b/i',
        '/@style\b/i',
        '/\{!!/',
        '/@csrf\b/i',
        '/@method\b/i',
    ];

    protected function validateTemplateMarkup(Validator $validator): void
    {
        foreach ($this->markupFields as $field) {
            $value = $this->input($field);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $detected = null;

            foreach ($this->forbiddenPatterns as $pattern) {
                if (preg_match($pattern, $value) === 1) {
                    $detected = $pattern;
                    break;
                }
            }

            if ($detected !== null) {
                $validator->errors()->add(
                    $field,
                    'قالب شامل کد PHP یا دستورات Blade غیرمجاز است ('.$this->humanPattern($detected).'). فقط سینتکس امن موتور قالب مجاز است.',
                );
            }
        }
    }

    private function humanPattern(string $pattern): string
    {
        return match ($pattern) {
            '/<\?/' => '<? ... ?>',
            '/\{!!/' => '{!! ... !!}',
            default => preg_replace('/[\/\\\\^$.*+?\[\]{}()|]/', '', trim($pattern, '/')) ?? $pattern,
        };
    }
}
