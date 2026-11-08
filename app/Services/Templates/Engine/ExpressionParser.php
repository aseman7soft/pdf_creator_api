<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;

/**
 * Recursive-descent parser for the restricted expression mini-language.
 *
 * Grammar (highest to lowest precedence):
 *   primary    := literal | variable | function-call | isset(...) | empty(...)
 *               | '(' expression ')' | array-literal
 *   postfix    := primary ( '.' identifier | '[' expression ']' )*
 *   unary      := ('!' | '-' | '+') unary | postfix
 *   multiplicative := unary (('*' | '/' | '%') unary)*
 *   additive   := multiplicative (('+' | '-' | '.') multiplicative)*
 *   comparison := additive (('<' | '<=' | '>' | '>=') additive)*
 *   equality   := comparison (('==' | '!=' | '===' | '!==') comparison)*
 *   logicalAnd := equality ('&&' equality)*
 *   logicalOr  := logicalAnd ('||' logicalAnd)*
 *   coalesce   := logicalOr ('??' coalesce)?          (right associative)
 *   ternary    := coalesce ('?' (expression)? ':' expression)?
 */
final class ExpressionParser
{
    /**
     * Functions that templates may call. Everything else is rejected.
     *
     * @var list<string>
     */
    public const ALLOWED_FUNCTIONS = [
        'count',
        'now',
        'strlen',
        'mb_strlen',
        'round',
        'abs',
        'max',
        'min',
        'intval',
        'floatval',
        'jdate',
        'jdatetime',
        'number',
        'date',
    ];

    /**
     * Output filters supported by the renderer.
     *
     * @var array<string, array{0: int, 1: int}> filter name => [minArgs, maxArgs]
     */
    public const ALLOWED_FILTERS = [
        'number' => [0, 0],
        'jdate' => [0, 1],
        'jdatetime' => [0, 1],
        'upper' => [0, 0],
        'lower' => [0, 0],
        'trim' => [0, 0],
        'nl2br' => [0, 0],
        'default' => [0, 1],
        'truncate' => [0, 1],
        'json' => [0, 0],
    ];

    /**
     * @var list<array{type: string, value: mixed, offset: int}>
     */
    private array $tokens = [];

    private int $position = 0;

    public function __construct(
        private readonly ExpressionTokenizer $tokenizer = new ExpressionTokenizer,
    ) {}

    /**
     * Parse a complete expression from source.
     */
    public function parse(string $source): Expr
    {
        $this->tokens = $this->tokenizer->tokenize($source);
        $this->position = 0;

        $expression = $this->parseTernary();

        $next = $this->current();

        if ($next['type'] !== ExpressionTokenizer::T_EOF) {
            throw new TemplateRenderException('عبارت قالب معتبر نیست: بخش اضافی «'.$this->describe($next).'» یافت شد.');
        }

        return $expression;
    }

    /**
     * Parse a comma separated argument list (used by directives and filters).
     *
     * @return list<Expr>
     */
    public function parseArguments(string $source): array
    {
        if (trim($source) === '') {
            return [];
        }

        $this->tokens = $this->tokenizer->tokenize($source);
        $this->position = 0;

        $arguments = [];

        while (true) {
            $arguments[] = $this->parseTernary();

            $token = $this->current();

            if ($token['type'] === ExpressionTokenizer::T_EOF) {
                break;
            }

            if ($token['type'] === ExpressionTokenizer::T_PUNCTUATION && $token['value'] === ',') {
                $this->position++;

                continue;
            }

            throw new TemplateRenderException('فهرست پارامترها معتبر نیست: «'.$this->describe($token).'» یافت شد.');
        }

        return $arguments;
    }

    /**
     * Parse a single filter spec such as `number`, `default('x')` or `truncate(80)`.
     *
     * @return array{name: string, args: list<Expr>}
     */
    public function parseFilter(string $source): array
    {
        $source = trim($source);

        if ($source === '') {
            throw new TemplateRenderException('فیلتر خالی در قالب مجاز نیست.');
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(?:\((.*)\))?$/s', $source, $matches) !== 1) {
            throw new TemplateRenderException('فیلتر «'.$this->shorten($source).'» معتبر نیست.');
        }

        $name = strtolower($matches[1]);

        if (! array_key_exists($name, self::ALLOWED_FILTERS)) {
            throw new TemplateRenderException('فیلتر «'.$name.'» در قالب پشتیبانی نمی‌شود.');
        }

        $arguments = isset($matches[2]) ? $this->parseArguments($matches[2]) : [];

        [$min, $max] = self::ALLOWED_FILTERS[$name];

        if (count($arguments) < $min || count($arguments) > $max) {
            throw new TemplateRenderException('تعداد پارامترهای فیلتر «'.$name.'» معتبر نیست.');
        }

        return ['name' => $name, 'args' => $arguments];
    }

    private function parseTernary(): Expr
    {
        $condition = $this->parseCoalesce();

        $token = $this->current();

        if ($token['type'] === ExpressionTokenizer::T_OPERATOR && $token['value'] === '?') {
            $this->position++;
            $this->skipWhitespaceTokens();

            $next = $this->current();

            if ($next['type'] === ExpressionTokenizer::T_OPERATOR && $next['value'] === ':') {
                $this->position++;
                $whenFalse = $this->parseTernary();

                return Expr::ternary($condition, null, $whenFalse);
            }

            $whenTrue = $this->parseTernary();
            $this->expectOperator(':');
            $whenFalse = $this->parseTernary();

            return Expr::ternary($condition, $whenTrue, $whenFalse);
        }

        return $condition;
    }

    private function parseCoalesce(): Expr
    {
        $left = $this->parseLogicalOr();
        $token = $this->current();

        if ($token['type'] === ExpressionTokenizer::T_OPERATOR && $token['value'] === '??') {
            $this->position++;

            return Expr::binary('??', $left, $this->parseCoalesce());
        }

        return $left;
    }

    private function parseLogicalOr(): Expr
    {
        $left = $this->parseLogicalAnd();

        while (($token = $this->current())['type'] === ExpressionTokenizer::T_OPERATOR && $token['value'] === '||') {
            $this->position++;
            $left = Expr::binary('||', $left, $this->parseLogicalAnd());
        }

        return $left;
    }

    private function parseLogicalAnd(): Expr
    {
        $left = $this->parseEquality();

        while (($token = $this->current())['type'] === ExpressionTokenizer::T_OPERATOR && $token['value'] === '&&') {
            $this->position++;
            $left = Expr::binary('&&', $left, $this->parseEquality());
        }

        return $left;
    }

    private function parseEquality(): Expr
    {
        $left = $this->parseComparison();

        while (true) {
            $token = $this->current();

            if ($token['type'] !== ExpressionTokenizer::T_OPERATOR || ! in_array($token['value'], ['==', '!=', '===', '!=='], true)) {
                return $left;
            }

            $this->position++;
            $left = Expr::binary((string) $token['value'], $left, $this->parseComparison());
        }
    }

    private function parseComparison(): Expr
    {
        $left = $this->parseAdditive();

        while (true) {
            $token = $this->current();

            if ($token['type'] !== ExpressionTokenizer::T_OPERATOR || ! in_array($token['value'], ['<', '<=', '>', '>='], true)) {
                return $left;
            }

            $this->position++;
            $left = Expr::binary((string) $token['value'], $left, $this->parseAdditive());
        }
    }

    private function parseAdditive(): Expr
    {
        $left = $this->parseMultiplicative();

        while (true) {
            $token = $this->current();

            if ($token['type'] !== ExpressionTokenizer::T_OPERATOR || ! in_array($token['value'], ['+', '-', '.'], true)) {
                return $left;
            }

            $this->position++;
            $left = Expr::binary((string) $token['value'], $left, $this->parseMultiplicative());
        }
    }

    private function parseMultiplicative(): Expr
    {
        $left = $this->parseUnary();

        while (true) {
            $token = $this->current();

            if ($token['type'] !== ExpressionTokenizer::T_OPERATOR || ! in_array($token['value'], ['*', '/', '%'], true)) {
                return $left;
            }

            $this->position++;
            $left = Expr::binary((string) $token['value'], $left, $this->parseUnary());
        }
    }

    private function parseUnary(): Expr
    {
        $token = $this->current();

        if ($token['type'] === ExpressionTokenizer::T_OPERATOR && in_array($token['value'], ['!', '-', '+'], true)) {
            $this->position++;

            return Expr::unary((string) $token['value'], $this->parseUnary());
        }

        return $this->parsePostfix();
    }

    private function parsePostfix(): Expr
    {
        $expression = $this->parsePrimary();

        while (true) {
            $token = $this->current();

            if ($token['type'] === ExpressionTokenizer::T_OPERATOR && $token['value'] === '.') {
                $next = $this->tokens[$this->position + 1] ?? ['type' => ExpressionTokenizer::T_EOF, 'value' => null, 'offset' => 0];

                // A dot is property access only when followed by a property
                // name; otherwise it is the string-concatenation operator and
                // is handled by the additive parser.
                if ($next['type'] !== ExpressionTokenizer::T_IDENTIFIER) {
                    return $expression;
                }

                $this->position++;
                $this->position++;
                $expression = Expr::property($expression, (string) $next['value']);

                continue;
            }

            if ($token['type'] === ExpressionTokenizer::T_PUNCTUATION && $token['value'] === '[') {
                $this->position++;
                $key = $this->parseTernary();
                $this->expectPunctuation(']');
                $expression = Expr::index($expression, $key);

                continue;
            }

            return $expression;
        }
    }

    private function parsePrimary(): Expr
    {
        $token = $this->current();

        switch ($token['type']) {
            case ExpressionTokenizer::T_NUMBER:
            case ExpressionTokenizer::T_STRING:
                $this->position++;

                return Expr::literal($token['value']);

            case ExpressionTokenizer::T_VARIABLE:
                $this->position++;

                return Expr::variable((string) $token['value']);

            case ExpressionTokenizer::T_IDENTIFIER:
                return $this->parseIdentifier();

            case ExpressionTokenizer::T_PUNCTUATION:
                if ($token['value'] === '(') {
                    $this->position++;
                    $expression = $this->parseTernary();
                    $this->expectPunctuation(')');

                    return $expression;
                }

                if ($token['value'] === '[') {
                    return $this->parseArrayLiteral();
                }

                break;
        }

        throw new TemplateRenderException('عبارت قالب معتبر نیست: «'.$this->describe($token).'» یافت نشد.');
    }

    private function parseIdentifier(): Expr
    {
        $token = $this->current();
        $this->position++;
        $name = (string) $token['value'];
        $lower = strtolower($name);

        if ($lower === 'true') {
            return Expr::literal(true);
        }

        if ($lower === 'false') {
            return Expr::literal(false);
        }

        if ($lower === 'null') {
            return Expr::literal(null);
        }

        $next = $this->current();
        $isCall = $next['type'] === ExpressionTokenizer::T_PUNCTUATION && $next['value'] === '(';

        if (! $isCall) {
            return Expr::variable($name);
        }

        $this->position++;

        if ($lower === 'isset') {
            $expression = $this->parseTernary();
            $this->expectPunctuation(')');

            return new Expr(Expr::KIND_ISSET, left: $expression);
        }

        if ($lower === 'empty') {
            $expression = $this->parseTernary();
            $this->expectPunctuation(')');

            return new Expr(Expr::KIND_EMPTY, left: $expression);
        }

        if (! in_array($lower, self::ALLOWED_FUNCTIONS, true)) {
            throw new TemplateRenderException('فراخوانی تابع «'.$lower.'» در قالب مجاز نیست.');
        }

        $arguments = $this->parseCallArguments();

        return Expr::function($lower, $arguments);
    }

    /**
     * @return list<Expr>
     */
    private function parseCallArguments(): array
    {
        $arguments = [];

        if ($this->currentIsPunctuation(')')) {
            $this->position++;

            return $arguments;
        }

        while (true) {
            $arguments[] = $this->parseTernary();

            if ($this->currentIsPunctuation(',')) {
                $this->position++;

                continue;
            }

            $this->expectPunctuation(')');

            return $arguments;
        }
    }

    private function parseArrayLiteral(): Expr
    {
        $this->expectPunctuation('[');
        $items = [];

        if ($this->currentIsPunctuation(']')) {
            $this->position++;

            return Expr::arrayLiteral($items);
        }

        while (true) {
            $items[] = $this->parseTernary();

            if ($this->currentIsPunctuation(',')) {
                $this->position++;

                if ($this->currentIsPunctuation(']')) {
                    $this->position++;

                    return Expr::arrayLiteral($items);
                }

                continue;
            }

            $this->expectPunctuation(']');

            return Expr::arrayLiteral($items);
        }
    }

    private function skipWhitespaceTokens(): void
    {
        // Tokenizer never emits whitespace tokens; kept for clarity of intent.
    }

    private function expectOperator(string $operator): void
    {
        $token = $this->current();

        if ($token['type'] !== ExpressionTokenizer::T_OPERATOR || $token['value'] !== $operator) {
            throw new TemplateRenderException('عملگر «'.$operator.'» در عبارت قالب یافت نشد.');
        }

        $this->position++;
    }

    private function expectPunctuation(string $punctuation): void
    {
        $token = $this->current();

        if ($token['type'] !== ExpressionTokenizer::T_PUNCTUATION || $token['value'] !== $punctuation) {
            throw new TemplateRenderException('علامت «'.$punctuation.'» در عبارت قالب یافت نشد.');
        }

        $this->position++;
    }

    private function currentIsPunctuation(string $punctuation): bool
    {
        $token = $this->current();

        return $token['type'] === ExpressionTokenizer::T_PUNCTUATION && $token['value'] === $punctuation;
    }

    /**
     * @return array{type: string, value: mixed, offset: int}
     */
    private function current(): array
    {
        return $this->tokens[$this->position] ?? ['type' => ExpressionTokenizer::T_EOF, 'value' => null, 'offset' => 0];
    }

    /**
     * @param  array{type: string, value: mixed, offset: int}  $token
     */
    private function describe(array $token): string
    {
        if ($token['type'] === ExpressionTokenizer::T_EOF) {
            return 'پایان عبارت';
        }

        return $this->shorten((string) $token['value']);
    }

    private function shorten(string $value): string
    {
        return strlen($value) > 40 ? substr($value, 0, 40).'…' : $value;
    }
}
