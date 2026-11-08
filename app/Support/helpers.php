<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;
use App\Support\Jalali;

if (! function_exists('jdate')) {
    /**
     * Format a date as a Jalali (Solar Hijri) string.
     */
    function jdate(DateTimeInterface|string|null $date, string $format = 'Y/m/d'): string
    {
        return Jalali::format($date, $format);
    }
}

if (! function_exists('jdatetime')) {
    /**
     * Format a date and time as a Jalali string.
     */
    function jdatetime(DateTimeInterface|string|null $date): string
    {
        return Jalali::format($date, 'Y/m/d H:i');
    }
}

if (! function_exists('fa_num')) {
    /**
     * Convert Latin digits to Persian digits.
     */
    function fa_num(mixed $value): string
    {
        return Jalali::digits(is_scalar($value) ? (string) $value : '');
    }
}

if (! function_exists('setting')) {
    /**
     * Read a persisted application setting.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
