<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

/**
 * Tagged-union AST node for a template expression.
 *
 * Expressions are parsed to this structure and evaluated by
 * ExpressionEvaluator; no PHP code is ever generated or executed.
 */
final class Expr
{
    public const KIND_LITERAL = 'literal';

    public const KIND_VARIABLE = 'variable';

    public const KIND_FUNCTION = 'function';

    public const KIND_UNARY = 'unary';

    public const KIND_BINARY = 'binary';

    public const KIND_TERNARY = 'ternary';

    public const KIND_ARRAY = 'array';

    public const KIND_INDEX = 'index';

    public const KIND_PROPERTY = 'property';

    public const KIND_ISSET = 'isset';

    public const KIND_EMPTY = 'empty';

    /**
     * @param  list<self>  $args
     */
    public function __construct(
        public readonly string $kind,
        public readonly mixed $value = null,
        public readonly ?self $left = null,
        public readonly ?self $right = null,
        public readonly ?self $third = null,
        public readonly array $args = [],
    ) {}

    public static function literal(mixed $value): self
    {
        return new self(self::KIND_LITERAL, $value);
    }

    public static function variable(string $name): self
    {
        return new self(self::KIND_VARIABLE, $name);
    }

    /**
     * @param  list<self>  $args
     */
    public static function function(string $name, array $args): self
    {
        return new self(self::KIND_FUNCTION, strtolower($name), args: $args);
    }

    public static function unary(string $operator, self $operand): self
    {
        return new self(self::KIND_UNARY, $operator, left: $operand);
    }

    public static function binary(string $operator, self $left, self $right): self
    {
        return new self(self::KIND_BINARY, $operator, left: $left, right: $right);
    }

    public static function ternary(self $condition, ?self $whenTrue, self $whenFalse): self
    {
        return new self(self::KIND_TERNARY, null, left: $condition, right: $whenTrue, third: $whenFalse);
    }

    /**
     * @param  list<self>  $items
     */
    public static function arrayLiteral(array $items): self
    {
        return new self(self::KIND_ARRAY, null, args: $items);
    }

    public static function index(self $subject, self $key): self
    {
        return new self(self::KIND_INDEX, null, left: $subject, args: [$key]);
    }

    public static function property(self $subject, string $name): self
    {
        return new self(self::KIND_PROPERTY, $name, left: $subject);
    }
}
