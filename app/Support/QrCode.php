<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Offline QR encoder (byte mode, ECC level M, versions 1–6).
 *
 * The SVG is built only from module coordinates, so reservation codes can be
 * drawn inside a PDF without a network call or an image library.
 */
final class QrCode
{
    private const MAX_VERSION = 6;

    /**
     * Byte-mode capacity at ECC M for versions 1–6.
     *
     * @var array<int, int>
     */
    private const BYTE_CAPACITY = [
        1 => 14,
        2 => 26,
        3 => 42,
        4 => 62,
        5 => 84,
        6 => 106,
    ];

    /**
     * ECC codewords per block, then data-codeword counts of each block.
     *
     * @var array<int, array{0: int, 1: list<int>}>
     */
    private const BLOCKS = [
        1 => [10, [16]],
        2 => [16, [28]],
        3 => [26, [44]],
        4 => [18, [32, 32]],
        5 => [22, [43, 43]],
        6 => [16, [27, 27, 27, 27]],
    ];

    /**
     * @var array<int, list<int>>
     */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
    ];

    /**
     * @var array<int, int>
     */
    private static array $exp = [];

    /**
     * @var array<int, int>
     */
    private static array $log = [];

    public static function svg(string $text, int $pixelSize = 128): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $bytes = self::payloadBytes($text);

        if ($bytes === []) {
            return '';
        }

        $modules = self::matrix($bytes);
        $count = count($modules);
        $quiet = 4;
        $dimension = $count + ($quiet * 2);
        $size = max(64, min(512, $pixelSize));
        $path = '';

        for ($y = 0; $y < $count; $y++) {
            $x = 0;

            while ($x < $count) {
                if (! $modules[$y][$x]) {
                    $x++;

                    continue;
                }

                $start = $x;

                while ($x < $count && $modules[$y][$x]) {
                    $x++;
                }

                $path .= 'M'.($start + $quiet).' '.($y + $quiet).'h'.($x - $start).'v1h-'.($x - $start).'z';
            }
        }

        return '<svg viewBox="0 0 '.$dimension.' '.$dimension.'" width="'.$size.'" height="'.$size.'" shape-rendering="crispEdges" role="img" aria-hidden="true"><rect width="100%" height="100%" fill="#ffffff"/><path fill="#12356b" d="'.$path.'"/></svg>';
    }

    /**
     * @return list<int>
     */
    private static function payloadBytes(string $text): array
    {
        $raw = $text;

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($raw, 'UTF-8')) {
            return [];
        }

        $encoded = $raw;

        while (strlen($encoded) > self::BYTE_CAPACITY[self::MAX_VERSION]) {
            if (function_exists('mb_substr')) {
                $raw = mb_substr($raw, 0, max(0, mb_strlen($raw, 'UTF-8') - 1), 'UTF-8');
            } else {
                $raw = substr($raw, 0, -1);
            }

            if ($raw === '') {
                return [];
            }

            $encoded = $raw;
        }

        return array_values(unpack('C*', $encoded) ?: []);
    }

    /**
     * @param  list<int>  $data
     * @return list<list<bool>>
     */
    public static function matrix(array $data): array
    {
        $version = self::versionFor(count($data));
        $codewords = self::dataCodewords($data, $version);
        $blocks = self::interleave($codewords, $version);
        $size = 21 + (($version - 1) * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));

        self::drawFunctionPatterns($matrix, $function, $version);
        self::drawData($matrix, $function, $blocks);
        $mask = self::applyBestMask($matrix, $function);
        self::drawFormat($matrix, $mask);

        return $matrix;
    }

    private static function versionFor(int $length): int
    {
        foreach (self::BYTE_CAPACITY as $version => $capacity) {
            if ($length <= $capacity) {
                return $version;
            }
        }

        return self::MAX_VERSION;
    }

    /**
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function dataCodewords(array $data, int $version): array
    {
        $total = array_sum(self::BLOCKS[$version][1]);
        $bits = '0100'.str_pad(decbin(count($data)), 8, '0', STR_PAD_LEFT);

        foreach ($data as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = $total * 8;
        $terminator = min(4, $capacityBits - strlen($bits));

        if ($terminator > 0) {
            $bits .= str_repeat('0', $terminator);
        }

        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $pad = ['11101100', '00010001'];
        $index = 0;

        while (strlen($bits) < $capacityBits) {
            $bits .= $pad[$index % 2];
            $index++;
        }

        $codewords = [];

        for ($offset = 0; $offset < $capacityBits; $offset += 8) {
            $codewords[] = bindec(substr($bits, $offset, 8));
        }

        return $codewords;
    }

    /**
     * @param  list<int>  $codewords
     * @return list<int>
     */
    private static function interleave(array $codewords, int $version): array
    {
        self::initField();
        [$ecLength, $dataLengths] = self::BLOCKS[$version];
        $blocks = [];
        $offset = 0;
        $maxData = 0;

        foreach ($dataLengths as $length) {
            $data = array_slice($codewords, $offset, $length);
            $offset += $length;
            $blocks[] = [$data, self::remainder($data, $ecLength)];
            $maxData = max($maxData, $length);
        }

        $out = [];

        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as [$data]) {
                if (isset($data[$i])) {
                    $out[] = $data[$i];
                }
            }
        }

        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($blocks as [, $ec]) {
                $out[] = $ec[$i];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function remainder(array $data, int $ecLength): array
    {
        $generator = self::generator($ecLength);
        $result = array_merge($data, array_fill(0, $ecLength, 0));

        foreach ($data as $index => $byte) {
            $factor = $result[$index];

            if ($factor === 0) {
                continue;
            }

            foreach ($generator as $position => $coefficient) {
                $result[$index + $position + 1] ^= self::multiply($coefficient, $factor);
            }
        }

        return array_slice($result, count($data));
    }

    /**
     * @return list<int>
     */
    private static function generator(int $degree): array
    {
        $polynomial = [1];

        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($polynomial) + 1, 0);

            foreach ($polynomial as $position => $coefficient) {
                $next[$position] ^= $coefficient;
                $next[$position + 1] ^= self::multiply($coefficient, self::$exp[$i]);
            }

            $polynomial = $next;
        }

        array_shift($polynomial);

        return $polynomial;
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     */
    private static function drawFunctionPatterns(array &$matrix, array &$function, int $version): void
    {
        $size = count($matrix);
        self::drawFinder($matrix, $function, 0, 0);
        self::drawFinder($matrix, $function, $size - 7, 0);
        self::drawFinder($matrix, $function, 0, $size - 7);

        for ($i = 0; $i < $size; $i++) {
            if (! $function[6][$i]) {
                self::paint($matrix, $function, 6, $i, $i % 2 === 0);
            }

            if (! $function[$i][6]) {
                self::paint($matrix, $function, $i, 6, $i % 2 === 0);
            }
        }

        $centers = self::ALIGNMENT[$version];

        foreach ($centers as $row) {
            foreach ($centers as $column) {
                if (($row === 6 && $column === 6)
                    || ($row === 6 && $column === $centers[count($centers) - 1])
                    || ($column === 6 && $row === $centers[count($centers) - 1])) {
                    continue;
                }

                self::drawAlignment($matrix, $function, $row, $column);
            }
        }

        self::paint($matrix, $function, $size - 8, 8, true);
        self::reserveFormat($function, $size);
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     */
    private static function drawFinder(array &$matrix, array &$function, int $row, int $column): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $targetRow = $row + $y;
                $targetColumn = $column + $x;

                if (! isset($matrix[$targetRow][$targetColumn])) {
                    continue;
                }

                $dark = $y >= 0 && $y <= 6 && $x >= 0 && $x <= 6
                    && ($y === 0 || $y === 6 || $x === 0 || $x === 6 || ($y >= 2 && $y <= 4 && $x >= 2 && $x <= 4));
                self::paint($matrix, $function, $targetRow, $targetColumn, $dark);
            }
        }
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     */
    private static function drawAlignment(array &$matrix, array &$function, int $row, int $column): void
    {
        for ($y = -2; $y <= 2; $y++) {
            for ($x = -2; $x <= 2; $x++) {
                $dark = max(abs($y), abs($x)) !== 1;
                self::paint($matrix, $function, $row + $y, $column + $x, $dark);
            }
        }
    }

    /**
     * @param  list<list<bool>>  $function
     */
    private static function reserveFormat(array &$function, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            $function[8][$i] = true;
            $function[$i][8] = true;
        }

        for ($i = 0; $i < 8; $i++) {
            $function[8][$size - 1 - $i] = true;
            $function[$size - 1 - $i][8] = true;
        }
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     * @param  list<int>  $codewords
     */
    private static function drawData(array &$matrix, array &$function, array $codewords): void
    {
        $bits = '';

        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $size = count($matrix);
        $index = 0;
        $length = strlen($bits);
        $upward = true;

        for ($column = $size - 1; $column > 0; $column -= 2) {
            if ($column === 6) {
                $column--;
            }

            $rows = $upward ? range($size - 1, 0) : range(0, $size - 1);

            foreach ($rows as $row) {
                foreach ([$column, $column - 1] as $targetColumn) {
                    if ($function[$row][$targetColumn]) {
                        continue;
                    }

                    $matrix[$row][$targetColumn] = $index < $length && $bits[$index] === '1';
                    $index++;
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     */
    private static function applyBestMask(array &$matrix, array $function): int
    {
        $original = $matrix;
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        $best = $matrix;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $original;

            foreach ($candidate as $row => $columns) {
                foreach ($columns as $column => $dark) {
                    if ($function[$row][$column]) {
                        continue;
                    }

                    if (self::mask($mask, $row, $column)) {
                        $candidate[$row][$column] = ! $dark;
                    }
                }
            }

            self::drawFormat($candidate, $mask);
            $score = self::penalty($candidate);

            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
                $best = $candidate;
            }
        }

        $matrix = $best;

        return $bestMask;
    }

    private static function mask(int $mask, int $row, int $column): bool
    {
        return match ($mask) {
            0 => ($row + $column) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $column % 3 === 0,
            3 => ($row + $column) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($column, 3)) % 2 === 0,
            5 => (($row * $column) % 2) + (($row * $column) % 3) === 0,
            6 => ((($row * $column) % 2) + (($row * $column) % 3)) % 2 === 0,
            default => ((($row + $column) % 2) + (($row * $column) % 3)) % 2 === 0,
        };
    }

    /**
     * @param  list<list<bool>>  $matrix
     */
    /**
     * @param  list<list<bool>>  $matrix
     */
    private static function drawFormat(array &$matrix, int $mask): void
    {
        $bits = self::formatBits($mask);
        $size = count($matrix);
        $bit = static fn (int $index): bool => (($bits >> $index) & 1) === 1;
        $verticalOffset = 0;
        $horizontalOffset = 0;

        for ($i = 0; $i < 8; $i++) {
            $low = $bit($i);
            $high = $bit(14 - $i);

            if ($i === 6) {
                $verticalOffset = 1;
                $horizontalOffset = 1;
            }

            $matrix[$i + $verticalOffset][8] = $low;
            $matrix[8][$i + $horizontalOffset] = $high;
            $matrix[8][$size - 1 - $i] = $low;
            $matrix[$size - 1 - $i][8] = $high;
        }

        // Timing-adjacent dark module. It sits on the lower format strip and stays black.
        $matrix[$size - 8][8] = true;
    }

    private static function formatBits(int $mask): int
    {
        $data = $mask;
        $remainder = $data << 10;

        for ($bit = 14; $bit >= 10; $bit--) {
            if ((($remainder >> $bit) & 1) === 1) {
                $remainder ^= 0b10100110111 << ($bit - 10);
            }
        }

        return (($data << 10) | ($remainder & 0b1111111111)) ^ 0b101010000010010;
    }

    /**
     * @param  list<list<bool>>  $matrix
     */
    private static function penalty(array $matrix): int
    {
        $size = count($matrix);
        $score = 0;

        foreach ([false, true] as $vertical) {
            for ($a = 0; $a < $size; $a++) {
                $run = 1;
                $previous = null;

                for ($b = 0; $b < $size; $b++) {
                    $dark = $vertical ? $matrix[$b][$a] : $matrix[$a][$b];

                    if ($dark === $previous) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $score += 3 + ($run - 5);
                        }

                        $previous = $dark;
                        $run = 1;
                    }
                }

                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        for ($row = 0; $row < $size - 1; $row++) {
            for ($column = 0; $column < $size - 1; $column++) {
                $color = $matrix[$row][$column];

                if ($color === $matrix[$row][$column + 1]
                    && $color === $matrix[$row + 1][$column]
                    && $color === $matrix[$row + 1][$column + 1]) {
                    $score += 3;
                }
            }
        }

        $finder = [true, false, true, true, true, false, true];

        foreach ([false, true] as $vertical) {
            for ($a = 0; $a < $size; $a++) {
                $line = [];

                for ($b = 0; $b < $size; $b++) {
                    $line[] = $vertical ? $matrix[$b][$a] : $matrix[$a][$b];
                }

                for ($index = 0; $index <= $size - 7; $index++) {
                    if (array_slice($line, $index, 7) !== $finder) {
                        continue;
                    }

                    $before = $index >= 4 && ! in_array(true, array_slice($line, $index - 4, 4), true);
                    $after = $index + 11 <= $size && ! in_array(true, array_slice($line, $index + 7, 4), true);

                    if ($before || $after) {
                        $score += 40;
                    }
                }
            }
        }

        $dark = 0;

        foreach ($matrix as $row) {
            foreach ($row as $module) {
                $dark += $module ? 1 : 0;
            }
        }

        $percent = (int) round(($dark * 100) / ($size * $size));
        $score += intdiv(abs($percent - 50), 5) * 10;

        return $score;
    }

    /**
     * @param  list<list<bool>>  $matrix
     * @param  list<list<bool>>  $function
     */
    private static function paint(array &$matrix, array &$function, int $row, int $column, bool $dark): void
    {
        $matrix[$row][$column] = $dark;
        $function[$row][$column] = true;
    }

    private static function initField(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $value;
            self::$log[$value] = $i;
            $value <<= 1;

            if (($value & 0x100) !== 0) {
                $value ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        return self::$exp[self::$log[$left] + self::$log[$right]];
    }
}
