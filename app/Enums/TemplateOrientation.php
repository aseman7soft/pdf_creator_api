<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function label(): string
    {
        return match ($this) {
            self::Portrait => 'عمودی (Portrait)',
            self::Landscape => 'افقی (Landscape)',
        };
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
