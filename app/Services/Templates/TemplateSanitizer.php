<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Exceptions\TemplateSanitizerException;

/**
 * Defensive HTML/CSS cleaner for stored templates and rendered documents.
 *
 * Templates may legitimately contain <style> blocks, data: URIs and local
 * asset:// fonts (these are required by the seeded templates), but they must
 * never contain executable markup or references that would make the PDF
 * engine fetch arbitrary remote resources.
 *
 * The sanitizer is intentionally regex based: templates are stored HTML
 * documents, not arbitrary web pages, and DOM (re)serialisation would alter
 * the exact markup relied on for print layout.
 */
final class TemplateSanitizer
{
    /**
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
     * @var list<string>
     */
    private const BLOCKED_ELEMENTS = ['script', 'iframe', 'object', 'embed', 'applet'];

    public function sanitize(string $html): string
    {
        $this->assertNoForbiddenConstructs($html);

        $html = $this->removeBlockedElements($html);
        $html = $this->removeDangerousHeadTags($html);
        $html = $this->removeEventAttributes($html);
        $html = $this->removeExternalStylesheetLinks($html);
        $html = $this->removeCssImports($html);
        $html = $this->sanitizeCssUrlFunctions($html);
        $html = $this->sanitizeUrlAttributes($html);
        $html = $this->removeDangerousSchemes($html);

        return $html;
    }

    /**
     * Reject templates that must never be stored/rendered at all.
     *
     * @throws TemplateSanitizerException
     */
    public function assertNoForbiddenConstructs(string $source): void
    {
        foreach (self::FORBIDDEN_CONSTRUCTS as [$pattern, $message]) {
            if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $offset = (int) $matches[0][1];
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;

                throw new TemplateSanitizerException($message, $line);
            }
        }
    }

    public function isSafe(string $html): bool
    {
        try {
            $this->assertNoForbiddenConstructs($html);

            return $this->sanitize($html) === $html;
        } catch (TemplateSanitizerException) {
            return false;
        }
    }

    /**
     * Remove executable / frame embedding elements, including their bodies.
     */
    private function removeBlockedElements(string $html): string
    {
        $pairedPattern = '/<\s*('.implode('|', self::BLOCKED_ELEMENTS).')\b[^>]*>.*?<\s*\/\s*\1\s*>/is';
        $previous = null;

        while ($previous !== $html) {
            $previous = $html;
            $result = preg_replace($pairedPattern, '', $html);
            $html = $result ?? $html;
        }

        $tagPattern = '/<\s*\/?\s*('.implode('|', self::BLOCKED_ELEMENTS).'|frame|frameset|noframes)\b[^>]*>/i';
        $result = preg_replace($tagPattern, '', $html);

        return $result ?? $html;
    }

    /**
     * Remove tags that could redirect/refresh or re-base relative URLs.
     */
    private function removeDangerousHeadTags(string $html): string
    {
        $html = preg_replace('/<\s*meta\b[^>]*http-equiv\s*=\s*(["\']?)\s*refresh\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\s*base\b[^>]*>/i', '', $html) ?? $html;

        return $html;
    }

    private function removeEventAttributes(string $html): string
    {
        $pattern = '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = preg_replace($pattern, '', $html);

            if ($result === null || $result === $html) {
                break;
            }

            $html = $result;
        }

        return $html;
    }

    private function removeDangerousSchemes(string $html): string
    {
        $html = preg_replace('/\b(?:java|vb)script\s*:/i', '', $html) ?? $html;
        $html = preg_replace('/\bdata\s*:\s*text\/html[^"\'\s)]*/i', '', $html) ?? $html;
        $html = preg_replace('/\bexpression\s*\(/i', '', $html) ?? $html;
        $html = preg_replace('/-moz-binding\s*:/i', '', $html) ?? $html;
        $html = preg_replace('/\bbehavior\s*:/i', '', $html) ?? $html;

        return $html;
    }

    /**
     * Keep only local (asset/data/relative) stylesheet links.
     */
    private function removeExternalStylesheetLinks(string $html): string
    {
        $result = preg_replace_callback('/<link\b[^>]*>/i', function (array $matches): string {
            $tag = $matches[0];

            if (preg_match('/href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $tag, $hrefMatch) !== 1) {
                return $tag;
            }

            $url = trim(trim($hrefMatch[1]), "\"'");

            return $this->isSafeUrl($url) ? $tag : '';
        }, $html);

        return $result ?? $html;
    }

    /**
     * CSS @import may load remote stylesheets; templates use asset:// @font-face
     * rules instead, so every @import is removed.
     */
    private function removeCssImports(string $html): string
    {
        $result = preg_replace('/@import\b[^;]*;?/i', '', $html);

        return $result ?? $html;
    }

    private function sanitizeCssUrlFunctions(string $html): string
    {
        $result = preg_replace_callback('/url\(\s*(["\']?)(.*?)\1\s*\)/is', function (array $matches): string {
            $url = trim($matches[2]);

            return $this->isSafeUrl($url) ? $matches[0] : "url('')";
        }, $html);

        return $result ?? $html;
    }

    private function sanitizeUrlAttributes(string $html): string
    {
        $pattern = '/\b(href|src|xlink:href|poster|action|formaction|background|srcdoc)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i';

        $result = preg_replace_callback($pattern, function (array $matches): string {
            $attribute = $matches[1];
            $rawValue = $matches[2];
            $url = trim(trim($rawValue, "\"'"));

            if ($this->isSafeUrl($url)) {
                return $matches[0];
            }

            return $attribute.'=""';
        }, $html);

        return $result ?? $html;
    }

    /**
     * A URL is safe when it is relative, an in-document anchor, a local
     * asset:// handle, or a data: URI whose media type is not HTML.
     */
    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        if (str_starts_with(strtolower($url), 'asset://')) {
            return preg_match('/^asset:\/\/[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $url) === 1;
        }

        if (str_starts_with(strtolower($url), 'data:')) {
            return preg_match('/^data:(?:image|font|application)\/[a-z0-9.+-]+/i', $url) === 1;
        }

        if (str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1) {
            return false;
        }

        return true;
    }
}
