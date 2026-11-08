<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;

/**
 * Lexer for the Blade-like template syntax.
 *
 * Produces a flat token stream:
 *  - text      : literal HTML (including <style>/<script> bodies, where "@" is
 *                handled as plain text so CSS at-rules are never mistaken for
 *                template directives),
 *  - output    : `{{ expression | filters }}`,
 *  - comment   : `{{-- ... --}}`,
 *  - directive : `@name(arguments)` for the supported directive set,
 *  - eof       : end-of-template sentinel.
 *
 * Unknown `@word` sequences are kept as literal text (so e-mail addresses and
 * CSS survive); forbidden constructs are rejected up-front by the renderer and
 * sanitizer.
 */
final class TemplateLexer
{
    public const T_TEXT = 'text';

    public const T_OUTPUT = 'output';

    public const T_COMMENT = 'comment';

    public const T_DIRECTIVE = 'directive';

    public const T_EOF = 'eof';

    /**
     * Directives understood by the engine, with or without a block.
     *
     * @var list<string>
     */
    public const DIRECTIVES = [
        'if', 'elseif', 'else', 'endif',
        'unless', 'endunless',
        'foreach', 'endforeach',
        'isset', 'endisset',
        'empty', 'endempty',
        'jdate', 'date', 'number',
    ];

    /**
     * @return list<array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}>
     */
    public function tokenize(string $html): array
    {
        $tokens = [];
        $length = strlen($html);
        $index = 0;
        $textStart = 0;
        $rawRegion = false;

        while ($index < $length) {
            $char = $html[$index];

            if ($char === '{' && substr_compare($html, '{{', $index, 2) === 0) {
                if (substr_compare($html, '{{--', $index, 4) === 0) {
                    $this->flushText($tokens, $html, $textStart, $index);
                    $close = strpos($html, '--}}', $index + 4);

                    if ($close === false) {
                        throw new TemplateRenderException('کامنت قالب بسته نشده است.', $this->lineAt($html, $index));
                    }

                    $tokens[] = ['type' => self::T_COMMENT, 'line' => $this->lineAt($html, $index)];
                    $index = $close + 4;
                    $textStart = $index;

                    continue;
                }

                $close = $this->findClosing($html, $index + 2, '}}');

                if ($close === null) {
                    throw new TemplateRenderException('علامت خروجی {{ بسته نشده است؛ }} جا افتاده است.', $this->lineAt($html, $index));
                }

                $this->flushText($tokens, $html, $textStart, $index);
                $tokens[] = [
                    'type' => self::T_OUTPUT,
                    'value' => substr($html, $index + 2, $close - $index - 2),
                    'line' => $this->lineAt($html, $index),
                ];
                $index = $close + 2;
                $textStart = $index;

                continue;
            }

            if ($char === '@' && $rawRegion === false) {
                if (preg_match('/\G@([A-Za-z_][A-Za-z0-9_]*)/', $html, $matches, 0, $index) === 1) {
                    $name = strtolower($matches[1]);

                    if (in_array($name, self::DIRECTIVES, true)) {
                        $this->flushText($tokens, $html, $textStart, $index);
                        $line = $this->lineAt($html, $index);
                        $nameEnd = $index + strlen($matches[0]);
                        $cursor = $nameEnd + strspn($html, " \t", $nameEnd);
                        $args = null;

                        if ($cursor < $length && $html[$cursor] === '(') {
                            $close = $this->findClosingParen($html, $cursor);

                            if ($close === null) {
                                throw new TemplateRenderException('پرانتز دستور @'.$name.' بسته نشده است.', $line);
                            }

                            $args = substr($html, $cursor + 1, $close - $cursor - 1);
                            $index = $close + 1;
                        } else {
                            $index = $nameEnd;
                        }

                        $tokens[] = [
                            'type' => self::T_DIRECTIVE,
                            'name' => $name,
                            'args' => $args,
                            'line' => $line,
                        ];
                        $textStart = $index;

                        continue;
                    }
                }

                $index++;

                continue;
            }

            if ($char === '<' && $rawRegion === false
                && preg_match('/\G<(style|script)\b/i', $html, $matches, 0, $index) === 1
            ) {
                $rawRegion = strtolower($matches[1]);
                $index += strlen($matches[0]);

                continue;
            }

            if ($char === '<' && $rawRegion !== false) {
                $closing = '</'.$rawRegion;

                if (strncasecmp(substr($html, $index, strlen($closing)), $closing, strlen($closing)) === 0) {
                    $rawRegion = false;
                    $index += strlen($closing);

                    continue;
                }
            }

            if ($char === '}' && $rawRegion === false && substr_compare($html, '}}', $index, 2) === 0) {
                throw new TemplateRenderException('علامت بسته شدن }} بدون شروع {{ یافت شد.', $this->lineAt($html, $index));
            }

            $index++;
        }

        $this->flushText($tokens, $html, $textStart, $length);
        $tokens[] = ['type' => self::T_EOF, 'line' => $this->lineAt($html, $length)];

        return $tokens;
    }

    /**
     * @param  list<array{type: string, value?: string|null, name?: string|null, args?: string|null, line: int}>  $tokens
     */
    private function flushText(array &$tokens, string $html, int $start, int $end): void
    {
        if ($end <= $start) {
            return;
        }

        $tokens[] = [
            'type' => self::T_TEXT,
            'value' => substr($html, $start, $end - $start),
            'line' => $this->lineAt($html, $start),
        ];
    }

    private function lineAt(string $html, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }

        return substr_count(substr($html, 0, $offset), "\n") + 1;
    }

    private function findClosing(string $html, int $start, string $needle): ?int
    {
        $length = strlen($html);
        $needleLength = strlen($needle);
        $index = $start;

        while ($index < $length) {
            $char = $html[$index];

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $index++;

                while ($index < $length) {
                    if ($html[$index] === '\\') {
                        $index += 2;

                        continue;
                    }

                    if ($html[$index] === $quote) {
                        $index++;

                        break;
                    }

                    $index++;
                }

                continue;
            }

            if (substr_compare($html, $needle, $index, $needleLength) === 0) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    private function findClosingParen(string $html, int $openPosition): ?int
    {
        $length = strlen($html);
        $depth = 1;
        $index = $openPosition + 1;

        while ($index < $length) {
            $char = $html[$index];

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $index++;

                while ($index < $length) {
                    if ($html[$index] === '\\') {
                        $index += 2;

                        continue;
                    }

                    if ($html[$index] === $quote) {
                        $index++;

                        break;
                    }

                    $index++;
                }

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }

            $index++;
        }

        return null;
    }
}
