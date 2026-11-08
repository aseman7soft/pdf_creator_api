<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplatePageSize: string
{
    case A4 = 'A4';
    case A3 = 'A3';
    case A5 = 'A5';
    case Letter = 'Letter';
    case Legal = 'Legal';
    case Custom = 'Custom';

    public function label(): string
    {
        return match ($this) {
            self::A4 => 'A4 (۲۱۰ × ۲۹۷ میلی‌متر)',
            self::A3 => 'A3 (۲۹۷ × ۴۲۰ میلی‌متر)',
            self::A5 => 'A5 (۱۴۸ × ۲۱۰ میلی‌متر)',
            self::Letter => 'Letter (۸.۵ × ۱۱ اینچ)',
            self::Legal => 'Legal (۸.۵ × ۱۴ اینچ)',
            self::Custom => 'اندازه سفارشی',
        };
    }

    /**
     * Millimetre dimensions. `Custom` receives explicit width/height from the
     * template PDF options.
     *
     * @return array{width: float, height: float}|null
     */
    public function dimensions(): ?array
    {
        $formats = config('pdf.page_formats', []);

        return $formats[$this->value] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
