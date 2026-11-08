<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;

/**
 * Parses the token stream produced by TemplateLexer into an immutable AST.
 *
 * All syntax problems surface as TemplateRenderException with a Persian
 * message and the 1-based source line. The parser never generates PHP and
 * never exposes raw template source in error messages.
 */
final class TemplateParser
{
    /**
     * Directives that close a block and must be handled by their opener.
     *
     * @var list<string>
     */
    private const CLOSING_DIRECTIVES = [
        'elseif', 'else', 'endif', 'endforeach', 'endunless', 'endisset', 'endempty',
    ];

    /**
     * @var list<array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}>
     */
    private array $tokens = [];

    private int $position = 0;

    /**
     * Opening lines of the blocks currently being parsed (for diagnostics).
     *
     * @var list<int>
     */
    private array $openBlocks = [];

    public function __construct(
        private readonly TemplateLexer $lexer = new TemplateLexer,
        private readonly ExpressionParser $expressions = new ExpressionParser,
    ) {}

    /**
     * @return list<Node>
     */
    public function parse(string $html): array
    {
        $this->tokens = $this->lexer->tokenize($html);
        $this->position = 0;
        $this->openBlocks = [];

        $nodes = $this->parseNodes([]);

        $token = $this->current();

        if ($token['type'] !== TemplateLexer::T_EOF) {
            throw new TemplateRenderException('دستور پایانی غیرمنتظره در قالب یافت شد.', $token['line']);
        }

        return $nodes;
    }

    /**
     * @param  list<string>  $stopDirectives
     * @return list<Node>
     */
    private function parseNodes(array $stopDirectives): array
    {
        $nodes = [];

        while (true) {
            $token = $this->current();

            if ($token['type'] === TemplateLexer::T_EOF) {
                if ($stopDirectives !== []) {
                    $line = $this->openBlocks === [] ? $token['line'] : $this->openBlocks[count($this->openBlocks) - 1];

                    throw new TemplateRenderException(
                        'دستور '.$this->openingDirectiveLabel($stopDirectives).' بسته نشده است.',
                        $line,
                    );
                }

                return $nodes;
            }

            if ($token['type'] === TemplateLexer::T_TEXT) {
                $this->position++;
                $nodes[] = Node::text((string) ($token['value'] ?? ''), $token['line']);

                continue;
            }

            if ($token['type'] === TemplateLexer::T_COMMENT) {
                $this->position++;

                continue;
            }

            if ($token['type'] === TemplateLexer::T_OUTPUT) {
                $this->position++;
                $nodes[] = $this->parseOutputToken($token);

                continue;
            }

            $name = (string) ($token['name'] ?? '');

            if (in_array($name, $stopDirectives, true)) {
                return $nodes;
            }

            if (in_array($name, self::CLOSING_DIRECTIVES, true)) {
                throw new TemplateRenderException('دستور @'.$name.' بدون بلوک بازکننده متناظر یافت شد.', $token['line']);
            }

            $parsed = match ($name) {
                'if' => $this->parseIf(),
                'unless' => $this->parseUnless(),
                'foreach' => $this->parseForeach(),
                'isset' => $this->parseCondition('isset'),
                'empty' => $this->parseCondition('empty'),
                'jdate', 'date', 'number' => $this->parseInlineFormatDirective(),
                default => throw new TemplateRenderException('دستور @'.$name.' در قالب پشتیبانی نمی‌شود.', $token['line']),
            };

            foreach ($parsed as $node) {
                $nodes[] = $node;
            }
        }
    }

    /**
     * @return list<Node>
     */
    private function parseIf(): array
    {
        $open = $this->expectDirective('if');
        $this->openBlocks[] = $open['line'];

        try {
            $branches = [];
            $condition = $this->parseConditionExpression($open);

            while (true) {
                $children = $this->parseNodes(['elseif', 'else', 'endif']);
                $branches[] = ['expr' => $condition, 'children' => $children];

                $token = $this->current();
                $name = (string) ($token['name'] ?? '');

                if ($name === 'elseif') {
                    $this->position++;
                    $condition = $this->parseConditionExpression($token);

                    continue;
                }

                if ($name === 'else') {
                    $this->assertDirectiveHasNoArguments($token, 'else');
                    $this->position++;
                    $elseChildren = $this->parseNodes(['endif']);
                    $closing = $this->current();

                    if (($closing['name'] ?? null) !== 'endif') {
                        throw new TemplateRenderException('دستور @if بسته نشده است؛ @endif جا افتاده است.', $open['line']);
                    }

                    $this->position++;

                    return [Node::if($branches, $elseChildren, $open['line'])];
                }

                if ($name === 'endif') {
                    $this->assertDirectiveHasNoArguments($token, 'endif');
                    $this->position++;

                    return [Node::if($branches, [], $open['line'])];
                }

                throw new TemplateRenderException('دستور @if بسته نشده است؛ @endif جا افتاده است.', $open['line']);
            }
        } finally {
            array_pop($this->openBlocks);
        }
    }

    /**
     * @return list<Node>
     */
    private function parseUnless(): array
    {
        $open = $this->expectDirective('unless');
        $this->openBlocks[] = $open['line'];

        try {
            $condition = $this->parseConditionExpression($open);
            $children = $this->parseNodes(['else', 'endunless']);
            $token = $this->current();
            $elseChildren = [];

            if (($token['name'] ?? null) === 'else') {
                $this->assertDirectiveHasNoArguments($token, 'else');
                $this->position++;
                $elseChildren = $this->parseNodes(['endunless']);
                $token = $this->current();
            }

            if (($token['name'] ?? null) !== 'endunless') {
                throw new TemplateRenderException('دستور @unless بسته نشده است؛ @endunless جا افتاده است.', $open['line']);
            }

            $this->assertDirectiveHasNoArguments($token, 'endunless');
            $this->position++;

            return [Node::if(
                [['expr' => Expr::unary('!', $condition), 'children' => $children]],
                $elseChildren,
                $open['line'],
            )];
        } finally {
            array_pop($this->openBlocks);
        }
    }

    /**
     * @return list<Node>
     */
    private function parseForeach(): array
    {
        $open = $this->expectDirective('foreach');
        $source = trim((string) ($open['args'] ?? ''));

        if ($source === '' || preg_match('/^(.*?)\s+as\s+(.+)$/is', $source, $matches) !== 1) {
            throw new TemplateRenderException('ساختار @foreach معتبر نیست؛ نمونه صحیح: @foreach($items as $item)', $open['line']);
        }

        $iterableSource = trim($matches[1]);
        $variableSource = trim($matches[2]);
        $valueVariable = null;
        $keyVariable = null;

        if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)\s*=>\s*\$([A-Za-z_][A-Za-z0-9_]*)$/', $variableSource, $variableMatches) === 1) {
            $keyVariable = $variableMatches[1];
            $valueVariable = $variableMatches[2];
        } elseif (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)$/', $variableSource, $variableMatches) === 1) {
            $valueVariable = $variableMatches[1];
        } else {
            throw new TemplateRenderException('متغیر حلقه @foreach معتبر نیست.', $open['line']);
        }

        $iterable = $this->parseExpression($iterableSource, $open['line'], 'ورودی حلقه @foreach');
        $this->openBlocks[] = $open['line'];

        try {
            $children = $this->parseNodes(['endforeach']);
            $closing = $this->current();

            if (($closing['name'] ?? null) !== 'endforeach') {
                throw new TemplateRenderException('دستور @foreach بسته نشده است؛ @endforeach جا افتاده است.', $open['line']);
            }

            $this->assertDirectiveHasNoArguments($closing, 'endforeach');
            $this->position++;

            return [Node::foreach($iterable, $valueVariable, $keyVariable, $children, $open['line'])];
        } finally {
            array_pop($this->openBlocks);
        }
    }

    /**
     * @return list<Node>
     */
    private function parseCondition(string $mode): array
    {
        $open = $this->expectDirective($mode);
        $this->openBlocks[] = $open['line'];

        try {
            $expression = $this->parseConditionExpression($open);
            $endDirective = 'end'.$mode;
            $children = $this->parseNodes([$endDirective]);
            $closing = $this->current();

            if (($closing['name'] ?? null) !== $endDirective) {
                throw new TemplateRenderException('دستور @'.$mode.' بسته نشده است؛ @'.$endDirective.' جا افتاده است.', $open['line']);
            }

            $this->assertDirectiveHasNoArguments($closing, $endDirective);
            $this->position++;

            return [Node::condition($mode, $expression, $children, $open['line'])];
        } finally {
            array_pop($this->openBlocks);
        }
    }

    /**
     * @return list<Node>
     */
    private function parseInlineFormatDirective(): array
    {
        $token = $this->current();
        $name = (string) ($token['name'] ?? '');
        $this->position++;

        try {
            $arguments = $this->expressions->parseArguments((string) ($token['args'] ?? ''));
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($token['line']);
        }

        if ($arguments === []) {
            throw new TemplateRenderException('دستور @'.$name.' حداقل به یک پارامتر نیاز دارد.', $token['line']);
        }

        if (count($arguments) > 2) {
            throw new TemplateRenderException('دستور @'.$name.' حداکثر دو پارامتر می‌پذیرد.', $token['line']);
        }

        $expression = Expr::function($name, $arguments);

        if ($name === 'number') {
            $expression = Expr::function('number', $arguments);
        }

        return [Node::output($expression, [], $token['line'])];
    }

    /**
     * @param  array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}  $token
     * @return array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}
     */
    private function expectDirective(string $name): array
    {
        $token = $this->current();

        if (($token['name'] ?? null) !== $name) {
            throw new TemplateRenderException('دستور @'.$name.' انتظار می‌رفت.', $token['line']);
        }

        $this->position++;

        return $token;
    }

    /**
     * @param  array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}  $token
     * @return array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}
     */
    private function parseConditionExpression(array $token): Expr
    {
        $name = (string) ($token['name'] ?? 'directive');
        $source = trim((string) ($token['args'] ?? ''));

        if ($source === '') {
            throw new TemplateRenderException('دستور @'.$name.' به یک عبارت نیاز دارد.', $token['line']);
        }

        return $this->parseExpression($source, $token['line'], 'شرط @'.$name);
    }

    /**
     * @param  array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}  $token
     */
    private function assertDirectiveHasNoArguments(array $token, string $name): void
    {
        if (($token['args'] ?? null) !== null) {
            throw new TemplateRenderException('دستور @'.$name.' پارامتر نمی‌پذیرد.', $token['line']);
        }
    }

    /**
     * @param  array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}  $token
     */
    private function parseOutputToken(array $token): Node
    {
        $source = trim((string) ($token['value'] ?? ''));

        if ($source === '') {
            throw new TemplateRenderException('خروجی {{ }} خالی است.', $token['line']);
        }

        $parts = $this->splitTopLevel($source, '|');
        $expressionSource = trim(array_shift($parts) ?? '');
        $filters = [];

        if ($expressionSource === '') {
            throw new TemplateRenderException('عبارت خروجی {{ }} خالی است.', $token['line']);
        }

        foreach ($parts as $filterSource) {
            $filters[] = $this->parseFilterSafely($filterSource, $token['line']);
        }

        return Node::output(
            $this->parseExpression($expressionSource, $token['line'], 'عبارت خروجی'),
            $filters,
            $token['line'],
        );
    }

    private function parseFilterSafely(string $source, int $line): array
    {
        try {
            return $this->expressions->parseFilter($source);
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($line);
        }
    }

    private function parseExpression(string $source, int $line, string $context): Expr
    {
        try {
            return $this->expressions->parse($source);
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($line);
        }
    }

    /**
     * Split a string on a top-level delimiter, respecting strings, parentheses
     * and brackets. For "|" a doubled "||" is never treated as a delimiter.
     *
     * @return list<string>
     */
    private function splitTopLevel(string $source, string $delimiter): array
    {
        $parts = [];
        $length = strlen($source);
        $current = '';
        $depth = 0;
        $index = 0;

        while ($index < $length) {
            $char = $source[$index];

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $current .= $char;
                $index++;

                while ($index < $length) {
                    $current .= $source[$index];

                    if ($source[$index] === '\\' && $index + 1 < $length) {
                        $index++;
                        $current .= $source[$index];
                    } elseif ($source[$index] === $quote) {
                        $index++;

                        break;
                    }

                    $index++;
                }

                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
                $index++;

                continue;
            }

            if ($char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
                $current .= $char;
                $index++;

                continue;
            }

            if ($depth === 0 && $char === $delimiter) {
                if ($delimiter === '|' && $index + 1 < $length && $source[$index + 1] === '|') {
                    $current .= '||';
                    $index += 2;

                    continue;
                }

                $parts[] = $current;
                $current = '';
                $index++;

                continue;
            }

            $current .= $char;
            $index++;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * @param  list<string>  $stopDirectives
     */
    private function openingDirectiveLabel(array $stopDirectives): string
    {
        $labels = [
            'endif' => '@if',
            'endforeach' => '@foreach',
            'endunless' => '@unless',
            'endisset' => '@isset',
            'endempty' => '@empty',
            'elseif' => '@if',
            'else' => '@if',
        ];

        foreach ($stopDirectives as $stopDirective) {
            if (isset($labels[$stopDirective])) {
                return $labels[$stopDirective];
            }
        }

        return 'بلوک';
    }

    /**
     * @return array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}
     */
    private function current(): array
    {
        return $this->tokens[$this->position] ?? ['type' => TemplateLexer::T_EOF, 'line' => 1];
    }
}
