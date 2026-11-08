<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Jalali;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JalaliTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int, int, int, int}>
     */
    public static function knownConversions(): array
    {
        return [
            'Nowruz 1403' => [2024, 3, 20, 1403, 1, 1],
            'Nowruz 1404' => [2025, 3, 21, 1404, 1, 1],
            'Nowruz 1405' => [2026, 3, 21, 1405, 1, 1],
            'Y2K' => [2000, 1, 1, 1378, 10, 11],
            'National Day 1405' => [2026, 11, 8, 1405, 8, 17],
            'Leap day 2024' => [2024, 2, 29, 1402, 12, 10],
        ];
    }

    #[DataProvider('knownConversions')]
    public function test_gregorian_to_jalali_known_dates(int $gy, int $gm, int $gd, int $jy, int $jm, int $jd): void
    {
        $this->assertSame([$jy, $jm, $jd], Jalali::fromGregorian($gy, $gm, $gd));
    }

    #[DataProvider('knownConversions')]
    public function test_jalali_to_gregorian_known_dates(int $gy, int $gm, int $gd, int $jy, int $jm, int $jd): void
    {
        $this->assertSame([$gy, $gm, $gd], Jalali::toGregorian($jy, $jm, $jd));
    }

    public function test_round_trip_all_days_of_a_year(): void
    {
        $date = new \DateTimeImmutable('2025-03-21');
        $end = new \DateTimeImmutable('2026-03-20');

        while ($date <= $end) {
            [$jy, $jm, $jd] = Jalali::fromGregorian(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            );

            $this->assertSame(
                [$date->format('Y'), $date->format('n'), $date->format('j')],
                array_map('strval', Jalali::toGregorian($jy, $jm, $jd)),
            );

            $date = $date->modify('+1 day');
        }
    }

    public function test_format_uses_persian_month_and_weekday_names(): void
    {
        $this->assertSame('1405/08/17', Jalali::format('2026-11-08'));
        $this->assertSame('17 آبان 1405', Jalali::format('2026-11-08', 'j F Y'));
        $this->assertSame('یکشنبه', Jalali::format('2026-11-08', 'l'));
        $this->assertSame('', Jalali::format(null));
    }

    public function test_persian_digits_conversion(): void
    {
        $this->assertSame('۱۴۰۵/۰۸/۱۷', Jalali::digits('1405/08/17'));
        $this->assertSame('', Jalali::digits(null));
    }
}
