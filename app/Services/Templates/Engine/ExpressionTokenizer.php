<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;

/**
 * Tokenizer for the restricted expression mini-language.
 *
 * It intentionally understands only literals, identifiers, variables, array
 * indexing, parentheses and a fixed set of operators. Object/static access
 * (`->`, `::`), backticks, assignment and function-call tricks are rejected
 * here, before any evaluation happens.
 */
final class ExpressionTokenizer
{
    public const T_NUMBER = 'number';

    public const T_STRING = 'string';

    public const T_VARIABLE = 'variable';

    public const T_IDENTIFIER = 'identifier';

    public const T_OPERATOR = 'operator';

    public const T_PUNCTUATION = 'punctuation';

    public const T_EOF = 'eof';

    /**
     * Two-character (or longer) operators, longest first.
     *
     * @var list<string>
     */
    private const MULTI_CHAR_OPERATORS = [
        '===', '!==', '??', '==', '!=', '<=', '>=', '&&', '||',
    ];

    /**
     * @var list<string>
     */
    private const SINGLE_CHAR_OPERATORS = [
        '+', '-', '*', '/', '%', '!', '<', '>', '?', ':', '.', '|', '=',
    ];

    /**
     * @var list<string>
     */
    private const PUNCTUATION = [',', '(', ')', '[', ']'];

    /**
     * Variable names that would expose the runtime if they ever resolved.
     *
     * @var list<string>
     */
    private const FORBIDDEN_VARIABLES = [
        'this', 'globals', '_server', '_env', '_get', '_post', '_request',
        '_cookie', '_files', '_session', 'argv', 'argc',
    ];

    /**
     * @return list<array{type: string, value: mixed, offset: int}>
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $index = 0;

        while ($index < $length) {
            $char = $source[$index];

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === "\0" || $char === "\v" || $char === "\f") {
                $index++;

                continue;
            }

            $offset = $index;

            if ($char === "'" || $char === '"') {
                [$value, $index] = $this->readString($source, $index);
                $tokens[] = ['type' => self::T_STRING, 'value' => $value, 'offset' => $offset];

                continue;
            }

            if ($char === '$') {
                $index++;
                $name = $this->readIdentifier($source, $index);

                if ($name === '') {
                    throw new TemplateRenderException('نام متغیر پس از $ یافت نشد.');
                }

                $lower = strtolower($name);

                if (in_array($lower, self::FORBIDDEN_VARIABLES, true)) {
                    throw new TemplateRenderException('دسترسی به متغیرهای سراسری و داخلی در قالب مجاز نیست.');
                }

                $tokens[] = ['type' => self::T_VARIABLE, 'value' => $name, 'offset' => $offset];

                continue;
            }

            if (ctype_digit($char)) {
                [$number] = $this->readNumber($source, $index);
                $tokens[] = ['type' => self::T_NUMBER, 'value' => $number, 'offset' => $offset];

                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $name = $this->readIdentifier($source, $index);
                $tokens[] = ['type' => self::T_IDENTIFIER, 'value' => $name, 'offset' => $offset];

                continue;
            }

            if ($char === '-' && $index + 1 < $length && $source[$index + 1] === '>') {
                throw new TemplateRenderException('فراخوانی متد روی اشیاء در قالب مجاز نیست.');
            }

            if ($char === ':' && $index + 1 < $length && $source[$index + 1] === ':') {
                throw new TemplateRenderException('دسترسی به کلاس‌ها و متدهای استاتیک در قالب مجاز نیست.');
            }

            if ($char === '`') {
                throw new TemplateRenderException('استفاده از کاراکتر backtick در قالب مجاز نیست.');
            }

            $matched = false;

            foreach (self::MULTI_CHAR_OPERATORS as $operator) {
                if (substr_compare($source, $operator, $index, strlen($operator)) === 0) {
                    $tokens[] = ['type' => self::T_OPERATOR, 'value' => $operator, 'offset' => $offset];
                    $index += strlen($operator);
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            if ($char === '=') {
                throw new TemplateRenderException('عملگر انتساب (=) در قالب مجاز نیست.');
            }

            if (in_array($char, self::SINGLE_CHAR_OPERATORS, true)) {
                $tokens[] = ['type' => self::T_OPERATOR, 'value' => $char, 'offset' => $offset];
                $index++;

                continue;
            }

            if (in_array($char, self::PUNCTUATION, true)) {
                $tokens[] = ['type' => self::T_PUNCTUATION, 'value' => $char, 'offset' => $offset];
                $index++;

                continue;
            }

            if ($char === '\\') {
                throw new TemplateRenderException('استفاده از فضای نام یا کاراکتر \\ در قالب مجاز نیست.');
            }

            throw new TemplateRenderException('کاراکتر غیرمجاز «'.$char.'» در عبارت قالب یافت شد.');
        }

        $tokens[] = ['type' => self::T_EOF, 'value' => null, 'offset' => $length];

        return $tokens;
    }

    private function isIdentifierStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_';
    }

    private function isIdentifierPart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }

    private function readIdentifier(string $source, int &$index): string
    {
        $length = strlen($source);
        $start = $index;

        while ($index < $length && $this->isIdentifierPart($source[$index])) {
            $index++;
        }

        return substr($source, $start, $index - $start);
    }

    /**
     * @return array{0: int|float, 1: int}
     */
    private function readNumber(string $source, int &$index): array
    {
        $length = strlen($source);
        $start = $index;

        while ($index < $length && ctype_digit($source[$index])) {
            $index++;
        }

        $isFloat = false;

        if ($index + 1 < $length && $source[$index] === '.' && ctype_digit($source[$index + 1])) {
            $isFloat = true;
            $index++;

            while ($index < $length && ctype_digit($source[$index])) {
                $index++;
            }
        }

        $raw = substr($source, $start, $index - $start);

        return [$isFloat ? (float) $raw : (int) $raw, $index];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readString(string $source, int &$index): array
    {
        $length = strlen($source);
        $quote = $source[$index];
        $index++;
        $value = '';

        while ($index < $length) {
            $char = $source[$index];

            if ($char === '\\') {
                if ($index + 1 >= $length) {
                    throw new TemplateRenderException('رشته متنی در عبارت بسته نشده است.');
                }

                $next = $source[$index + 1];
                $value .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    default => $next,
                };
                $index += 2;

                continue;
            }

            if ($char === $quote) {
                $index++;

                return [$value, $index];
            }

            $value .= $char;
            $index++;
        }

        throw new TemplateRenderException('رشته متنی در عبارت بسته نشده است.');
    }
}
