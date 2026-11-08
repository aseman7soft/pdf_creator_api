<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Exceptions\TemplateRenderException;
use App\Services\Templates\Engine\Node;
use App\Services\Templates\Engine\NodeRenderer;
use App\Services\Templates\Engine\TemplateParser;

/**
 * Safe, Blade-like template renderer with zero PHP execution.
 *
 * Syntax (docs/CONTRACTS.md §4):
 *   {{ expression | filter }}   escaped output; filters: number, jdate,
 *                               jdatetime, upper, lower, trim, nl2br,
 *                               default('x'), truncate(80), json
 *
 *   @if/@elseif/@else/@endif, @unless/@endunless, @isset/@endisset,
 *
 *   @empty/@endempty, @foreach(...)/@endforeach with loop.* metadata,
 *
 *   @jdate(expr[, 'j F Y']) / @date(expr[, 'Y-m-d']) / @number(expr),
 *   {{-- comments --}}
 *
 * Anything that could execute code (PHP tags, Blade compilation directives,
 * raw output blocks, method calls, static calls, $GLOBALS, backticks, dynamic
 * functions) is rejected or never emitted. Invalid syntax raises a
 * TemplateRenderException with a Persian message and source line.
 */
final class SafeTemplateRenderer
{
    private const MAX_COMPILED_TEMPLATES = 64;

    /**
     * Constructs that are forbidden before parsing. Each pair is
     * [pattern, user-facing Persian message].
     *
     * @var list<array{0: string, 1: string}>
     */
    private const FORBIDDEN_CONSTRUCTS = [
        ['/<\?/i', 'استفاده از کد PHP در قالب مجاز نیست.'],
        ['/\{!!|!!\}/', 'خروجی خام (unescaped) در قالب پشتیبانی نمی‌شود.'],
        ['/@(php|endphp)\b/i', 'استفاده از @php در قالب مجاز نیست.'],
        ['/@(include|extends|inject|use|section|endsection|yield|component|slot|livewire|verbatim)\b/i', 'این دستور Blade در قالب مجاز نیست.'],
        ['/@(eval|exec|system|shell_exec|passthru|popen|proc_open|assert|call_user_func|file_get_contents|fopen|unlink)\b/i', 'این دستور در قالب مجاز نیست.'],
    ];

    /**
     * @var array<string, list<Node>>
     */
    private array $compiled = [];

    public function __construct(
        private readonly TemplateParser $parser = new TemplateParser,
        private readonly NodeRenderer $renderer = new NodeRenderer,
    ) {}

    /**
     * Render a template string with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $html, array $data, string $locale = 'fa'): string
    {
        // The locale is accepted for contract compatibility and future
        // locale-aware formatting; escaping/formatting is locale-independent.
        unset($locale);

        self::assertNoForbiddenConstructs($html);

        return $this->renderer->render($this->compile($html), $data);
    }

    /**
     * Compile a template into its AST (cached per request for performance).
     *
     * @return list<Node>
     */
    public function compile(string $html): array
    {
        $key = hash('sha256', $html);

        if (isset($this->compiled[$key])) {
            return $this->compiled[$key];
        }

        $nodes = $this->parser->parse($html);

        if (count($this->compiled) >= self::MAX_COMPILED_TEMPLATES) {
            array_shift($this->compiled);
        }

        return $this->compiled[$key] = $nodes;
    }

    /**
     * Reject templates that try to escape the sandbox before parsing.
     *
     * @throws TemplateRenderException
     */
    public static function assertNoForbiddenConstructs(string $source): void
    {
        foreach (self::FORBIDDEN_CONSTRUCTS as [$pattern, $message]) {
            if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $offset = (int) $matches[0][1];
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;

                throw new TemplateRenderException($message, $line);
            }
        }
    }
}
