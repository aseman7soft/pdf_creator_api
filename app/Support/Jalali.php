<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Gregorian <-> Jalali (Solar Hijri) conversion.
 *
 * The conversion uses the well-known arithmetic algorithm (Borkowski / jdf)
 * which is accurate for the supported range (Gregorian 1900-2100) and needs
 * no external extension.
 */
final class Jalali
{
    /**
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    /**
     * @var array<int, string>
     */
    private const WEEKDAYS = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function fromGregorian(int $year, int $month, int $day): array
    {
        $daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gy2 = $month > 2 ? $year + 1 : $year;

        $days = 355666 + (365 * $year) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $day;

        for ($i = 0; $i < $month - 1; $i++) {
            $days += $daysInMonth[$i];
        }

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toGregorian(int $year, int $month, int $day): array
    {
        $jy = $year + 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8)
            + intdiv(($jy % 33) + 3, 4) + $day
            + ($month < 7 ? ($month - 1) * 31 : (($month - 7) * 30) + 186);

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0);
        $monthLengths = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gm = 0; $gm < 13 && $gd > $monthLengths[$gm]; $gm++) {
            $gd -= $monthLengths[$gm];
        }

        return [$gy, max(1, $gm), $gd];
    }

    /**
     * Convert a date to a Jalali formatted string.
     */
    public static function format(DateTimeInterface|string|null $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timezone = date_default_timezone_get();

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)->timezone($timezone)
            : Carbon::parse($date, $timezone);

        [$jy, $jm, $jd] = self::fromGregorian(
            (int) $carbon->format('Y'),
            (int) $carbon->format('n'),
            (int) $carbon->format('j'),
        );

        $replacements = [
            'Y' => (string) $jy,
            'y' => substr((string) $jy, -2),
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'F' => self::MONTHS[$jm] ?? '',
            'l' => self::WEEKDAYS[(int) $carbon->format('w')] ?? '',
            'H' => $carbon->format('H'),
            'i' => $carbon->format('i'),
            's' => $carbon->format('s'),
            'G' => $carbon->format('G'),
        ];

        return preg_replace_callback('/[YymndjFlHisG]/u', static fn (array $m): string => $replacements[$m[0]] ?? $m[0], $format) ?? $format;
    }

    /**
     * Human readable Jalali date, e.g. ۱۵ آبان ۱۴۰۵.
     */
    public static function human(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'j F Y');
    }

    /**
     * Jalali date + time, e.g. ۱۴۰۵/۰۸/۱۵ ۱۴:۳۰.
     */
    public static function dateTime(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'Y/m/d H:i');
    }

    public static function now(string $format = 'Y/m/d'): string
    {
        return self::format(now(), $format);
    }

    /**
     * Convert Latin digits to Persian digits.
     */
    public static function digits(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /**
     * Month name by number.
     */
    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }
}
