<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر کل',
            self::Manager => 'مدیر محتوا',
            self::Viewer => 'مشاهده‌گر',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'دسترسی کامل به همه بخش‌ها، تنظیمات و مدیریت کاربران',
            self::Manager => 'مدیریت قالب‌ها، پروژه‌ها، اسناد و صف',
            self::Viewer => 'مشاهده و دانلود اسناد، بدون امکان تغییر',
        };
    }

    public function canManage(): bool
    {
        return $this !== self::Viewer;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
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
