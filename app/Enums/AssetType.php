<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetType: string
{
    case Font = 'font';
    case Image = 'image';
    case Css = 'css';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Font => 'فونت',
            self::Image => 'تصویر / لوگو',
            self::Css => 'فایل CSS',
            self::Other => 'سایر',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Font => 'text-bg-warning',
            self::Image => 'text-bg-primary',
            self::Css => 'text-bg-info',
            self::Other => 'text-bg-secondary',
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
