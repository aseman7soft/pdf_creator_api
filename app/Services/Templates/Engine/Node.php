<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

/**
 * Tagged-union AST node for a compiled template construct.
 *
 * @phpstan-type FilterSpec array{name: string, args: list<Expr>}
 */
final class Node
{
    public const KIND_TEXT = 'text';

    public const KIND_OUTPUT = 'output';

    public const KIND_IF = 'if';

    public const KIND_FOREACH = 'foreach';

    public const KIND_CONDITION = 'condition';

    /**
     * @param  list<Node>  $children
     * @param  list<FilterSpec>  $filters
     * @param  list<array{expr: Expr, children: list<Node>}>  $branches
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $text = '',
        public readonly ?Expr $expr = null,
        public readonly array $filters = [],
        public readonly array $children = [],
        public readonly array $branches = [],
        public readonly ?string $valueVariable = null,
        public readonly ?string $keyVariable = null,
        public readonly ?string $conditionMode = null,
        public readonly int $line = 1,
    ) {}

    public static function text(string $text, int $line = 1): self
    {
        return new self(self::KIND_TEXT, text: $text, line: $line);
    }

    /**
     * @param  list<FilterSpec>  $filters
     */
    public static function output(Expr $expr, array $filters, int $line): self
    {
        return new self(self::KIND_OUTPUT, expr: $expr, filters: $filters, line: $line);
    }

    /**
     * @param  list<array{expr: Expr, children: list<Node>}>  $branches
     * @param  list<Node>  $elseChildren
     */
    public static function if(array $branches, array $elseChildren, int $line): self
    {
        return new self(self::KIND_IF, children: $elseChildren, branches: $branches, line: $line);
    }

    /**
     * @param  list<Node>  $children
     */
    public static function foreach(Expr $iterable, string $valueVariable, ?string $keyVariable, array $children, int $line): self
    {
        return new self(
            self::KIND_FOREACH,
            expr: $iterable,
            children: $children,
            valueVariable: $valueVariable,
            keyVariable: $keyVariable,
            line: $line,
        );
    }

    /**
     * @param  list<Node>  $children
     */
    public static function condition(string $mode, Expr $expr, array $children, int $line): self
    {
        return new self(
            self::KIND_CONDITION,
            expr: $expr,
            children: $children,
            conditionMode: $mode,
            line: $line,
        );
    }
}
