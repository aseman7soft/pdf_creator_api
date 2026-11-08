<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;
use App\Support\Jalali;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Evaluates the restricted expression AST against a plain array context.
 *
 * Security invariants:
 *  - only array (and read-only object property) lookups are allowed,
 *  - no method calls, no static calls, no dynamic function names,
 *  - only the allowlisted functions from ExpressionParser::ALLOWED_FUNCTIONS
 *    can be executed,
 *  - unknown variables resolve to null instead of crashing.
 */
final class ExpressionEvaluator
{
    private const MAX_FUNCTION_ARGUMENTS = 16;

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(Expr $expr, array $context): mixed
    {
        return match ($expr->kind) {
            Expr::KIND_LITERAL => $expr->value,
            Expr::KIND_VARIABLE => $this->variable((string) $expr->value, $context),
            Expr::KIND_ARRAY => array_map(fn (Expr $item): mixed => $this->evaluate($item, $context), $expr->args),
            Expr::KIND_INDEX => $this->indexValue($expr, $context),
            Expr::KIND_PROPERTY => $this->propertyValue($expr, $context),
            Expr::KIND_FUNCTION => $this->callFunction((string) $expr->value, $expr->args, $context),
            Expr::KIND_UNARY => $this->unary($expr, $context),
            Expr::KIND_BINARY => $this->binary($expr, $context),
            Expr::KIND_TERNARY => $this->ternary($expr, $context),
            Expr::KIND_ISSET => $this->evaluate($expr->left, $context) !== null,
            Expr::KIND_EMPTY => $this->isEmpty($this->evaluate($expr->left, $context)),
            default => throw new TemplateRenderException('ساختار عبارت قالب پشتیبانی نمی‌شود.'),
        };
    }

    /**
     * Applies output filters to an already evaluated value.
     *
     * @param  list<array{name: string, args: list<Expr>}>  $filters
     * @param  array<string, mixed>  $context
     * @return array{value: string, safe: bool} safe=true means the value already
     *                                          contains intentional HTML (nl2br)
     */
    public function applyFilters(mixed $value, array $filters, array $context): array
    {
        $safe = false;

        foreach ($filters as $filter) {
            $arguments = array_map(
                fn (Expr $argument): mixed => $this->evaluate($argument, $context),
                $filter['args'],
            );

            $value = match ($filter['name']) {
                'default' => ($value === null || $value === '')
                    ? ($arguments[0] ?? '')
                    : $value,
                'number' => $this->formatNumber($value),
                'jdate' => Jalali::format(
                    $this->toDateValue($value),
                    isset($arguments[0]) && is_string($arguments[0]) ? $arguments[0] : 'Y/m/d',
                ),
                'jdatetime' => Jalali::format(
                    $this->toDateValue($value),
                    isset($arguments[0]) && is_string($arguments[0]) ? $arguments[0] : 'Y/m/d H:i',
                ),
                'upper' => mb_strtoupper($this->stringify($value), 'UTF-8'),
                'lower' => mb_strtolower($this->stringify($value), 'UTF-8'),
                'trim' => trim($this->stringify($value)),
                'truncate' => $this->truncate($value, $arguments[0] ?? 80),
                'json' => $this->encodeJson($value),
                'nl2br' => $this->nl2br($value),
                default => throw new TemplateRenderException('فیلتر قالب پشتیبانی نمی‌شود.'),
            };

            if ($filter['name'] === 'nl2br') {
                $safe = true;
            }
        }

        return ['value' => $this->stringify($value), 'safe' => $safe];
    }

    /**
     * Convert any supported value to an escaped-safe plain string.
     */
    public function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
                return (string) (int) $value;
            }

            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function variable(string $name, array $context): mixed
    {
        return array_key_exists($name, $context) ? $context[$name] : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function indexValue(Expr $expr, array $context): mixed
    {
        $subject = $this->evaluate($expr->left, $context);
        $key = $this->evaluate($expr->args[0] ?? Expr::literal(null), $context);

        if (! is_array($subject)) {
            return null;
        }

        if (is_int($key) || is_string($key)) {
            return array_key_exists($key, $subject) ? $subject[$key] : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function propertyValue(Expr $expr, array $context): mixed
    {
        $subject = $this->evaluate($expr->left, $context);
        $name = (string) $expr->value;

        if (is_array($subject)) {
            return array_key_exists($name, $subject) ? $subject[$name] : null;
        }

        if ($subject instanceof \stdClass) {
            return property_exists($subject, $name) ? $subject->{$name} : null;
        }

        return null;
    }

    /**
     * @param  list<Expr>  $arguments
     * @param  array<string, mixed>  $context
     */
    private function callFunction(string $name, array $arguments, array $context): mixed
    {
        if (! in_array($name, ExpressionParser::ALLOWED_FUNCTIONS, true)) {
            throw new TemplateRenderException('فراخوانی تابع «'.$name.'» در قالب مجاز نیست.');
        }

        if (count($arguments) > self::MAX_FUNCTION_ARGUMENTS) {
            throw new TemplateRenderException('تعداد پارامترهای تابع در قالب بیش از حد مجاز است.');
        }

        $values = array_map(fn (Expr $argument): mixed => $this->evaluate($argument, $context), $arguments);

        return match ($name) {
            'count' => is_array($values[0] ?? null) ? count($values[0]) : 0,
            'now' => Carbon::now(),
            'strlen' => strlen($this->stringify($values[0] ?? null)),
            'mb_strlen' => mb_strlen($this->stringify($values[0] ?? null), 'UTF-8'),
            'round' => round($this->toNumber($values[0] ?? null), isset($values[1]) ? (int) $this->toNumber($values[1]) : 0),
            'abs' => abs($this->toNumber($values[0] ?? null)),
            'max' => $this->aggregate($values, true),
            'min' => $this->aggregate($values, false),
            'intval' => (int) $this->toNumber($values[0] ?? null),
            'floatval' => (float) $this->toNumber($values[0] ?? null),
            'jdate' => Jalali::format(
                $this->toDateValue($values[0] ?? null),
                isset($values[1]) && is_string($values[1]) ? $values[1] : 'Y/m/d',
            ),
            'jdatetime' => Jalali::format(
                $this->toDateValue($values[0] ?? null),
                isset($values[1]) && is_string($values[1]) ? $values[1] : 'Y/m/d H:i',
            ),
            'number' => $this->formatNumber($values[0] ?? null),
            'date' => $this->formatGregorianDate($values[0] ?? null, isset($values[1]) && is_string($values[1]) ? $values[1] : 'Y-m-d'),
            default => throw new TemplateRenderException('فراخوانی تابع «'.$name.'» در قالب مجاز نیست.'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function unary(Expr $expr, array $context): mixed
    {
        $value = $this->evaluate($expr->left, $context);

        return match ((string) $expr->value) {
            '!' => ! $this->truthy($value),
            '-' => -$this->toNumber($value),
            '+' => $this->toNumber($value),
            default => throw new TemplateRenderException('عملگر یکانی قالب پشتیبانی نمی‌شود.'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function binary(Expr $expr, array $context): mixed
    {
        $operator = (string) $expr->value;

        if ($operator === '??') {
            $left = $this->evaluate($expr->left, $context);

            return $left ?? $this->evaluate($expr->right, $context);
        }

        if ($operator === '&&') {
            return $this->truthy($this->evaluate($expr->left, $context))
                && $this->truthy($this->evaluate($expr->right, $context));
        }

        if ($operator === '||') {
            return $this->truthy($this->evaluate($expr->left, $context))
                || $this->truthy($this->evaluate($expr->right, $context));
        }

        $left = $this->evaluate($expr->left, $context);
        $right = $this->evaluate($expr->right, $context);

        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '===' => $left === $right,
            '!==' => $left !== $right,
            '<' => $this->comparable($left, $right, '<'),
            '<=' => $this->comparable($left, $right, '<='),
            '>' => $this->comparable($left, $right, '>'),
            '>=' => $this->comparable($left, $right, '>='),
            '+' => $this->toNumber($left) + $this->toNumber($right),
            '-' => $this->toNumber($left) - $this->toNumber($right),
            '*' => $this->toNumber($left) * $this->toNumber($right),
            '/' => $this->divide($left, $right),
            '%' => $this->modulo($left, $right),
            '.' => $this->stringify($left).$this->stringify($right),
            default => throw new TemplateRenderException('عملگر «'.$operator.'» در قالب پشتیبانی نمی‌شود.'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function ternary(Expr $expr, array $context): mixed
    {
        $condition = $this->truthy($this->evaluate($expr->left, $context));

        if ($expr->right === null) {
            return $condition ? $this->evaluate($expr->left, $context) : $this->evaluate($expr->third, $context);
        }

        return $condition
            ? $this->evaluate($expr->right, $context)
            : $this->evaluate($expr->third, $context);
    }

    private function comparable(mixed $left, mixed $right, string $operator): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        } elseif (is_string($left) && is_string($right)) {
            // Strings compare with a deterministic byte-wise ordering.
        } else {
            throw new TemplateRenderException('مقایسه دو مقدار ناسازگار در قالب مجاز نیست.');
        }

        return match ($operator) {
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => false,
        };
    }

    private function divide(mixed $left, mixed $right): int|float
    {
        $divisor = $this->toNumber($right);

        if ($divisor == 0) {
            throw new TemplateRenderException('تقسیم بر صفر در عبارت قالب مجاز نیست.');
        }

        return $this->toNumber($left) / $divisor;
    }

    private function modulo(mixed $left, mixed $right): int
    {
        $divisor = (int) $this->toNumber($right);

        if ($divisor === 0) {
            throw new TemplateRenderException('باقیمانده تقسیم بر صفر در عبارت قالب مجاز نیست.');
        }

        return (int) $this->toNumber($left) % $divisor;
    }

    private function truthy(mixed $value): bool
    {
        return (bool) $value;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === false || $value === 0 || $value === 0.0 || $value === '0' || $value === [];
    }

    private function toNumber(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            $trimmed = trim($value);

            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        throw new TemplateRenderException('عملیات ریاضی روی مقدار غیرعددی در قالب مجاز نیست.');
    }

    /**
     * @param  list<mixed>  $values
     */
    private function aggregate(array $values, bool $max): int|float
    {
        if (count($values) === 1 && is_array($values[0])) {
            $values = array_values($values[0]);
        }

        if ($values === []) {
            throw new TemplateRenderException('تابع max/min حداقل به یک مقدار نیاز دارد.');
        }

        $numbers = array_map(fn (mixed $value): int|float => $this->toNumber($value), $values);

        return $max ? max($numbers) : min($numbers);
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return $this->stringify($value);
        }

        $number = (float) $value;
        $decimals = (is_finite($number) && floor($number) === $number) ? 0 : 2;

        return number_format($number, $decimals, '.', ',');
    }

    private function truncate(mixed $value, mixed $limit): string
    {
        $limit = (int) (is_numeric($limit) ? $limit : 80);

        if ($limit <= 0) {
            $limit = 80;
        }

        $string = $this->stringify($value);

        if (mb_strlen($string, 'UTF-8') <= $limit) {
            return $string;
        }

        return rtrim(mb_substr($string, 0, $limit, 'UTF-8')).'…';
    }

    private function nl2br(mixed $value): string
    {
        return nl2br(htmlspecialchars($this->stringify($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    private function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    private function toDateValue(mixed $value): DateTimeInterface|string|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if (is_float($value) && is_finite($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    private function formatGregorianDate(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $this->toDateValue($value);

        if ($date === null) {
            return '';
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format($format);
        }

        try {
            return Carbon::parse($date, config('app.timezone'))->format($format);
        } catch (\Throwable) {
            return '';
        }
    }
}
