<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Enums\TemplateVariableType;
use App\Exceptions\TemplateRenderException;
use App\Models\Template;
use App\Services\Documents\Dto\PdfOptions;
use App\Services\Documents\Dto\RenderedDocument;
use App\Services\Security\ExternalUrlGuard;

/**
 * Renders a persisted Template into a RenderedDocument ready for Gotenberg.
 *
 * Responsibilities:
 *  - safe rendering of html/css/header/footer (no PHP execution),
 *  - HTML sanitisation (inline event handlers, javascript: URLs and external
 *    resource URLs are removed),
 *  - CSS injection into <head>,
 *  - image-variable resolution (asset://handle, data: URI, guarded remote URL),
 *  - asset://handle flattening into a filename => contents map.
 */
final class TemplateRenderService
{
    public function __construct(
        private readonly SafeTemplateRenderer $renderer,
        private readonly TemplateSanitizer $sanitizer,
        private readonly AssetResolver $assets,
        private readonly ExternalUrlGuard $urlGuard,
    ) {}

    /**
     * Render a template with the given (already validated) data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options  per-request PDF option overrides
     */
    public function render(Template $template, array $data, array $options = []): RenderedDocument
    {
        $locale = (string) ($template->locale ?? 'fa');
        [$preparedData, $extraAssets] = $this->resolveImageVariables($template, $data);

        $html = $this->sanitizer->sanitize($this->renderer->render((string) $template->html, $preparedData, $locale));

        if (trim($html) === '') {
            throw new TemplateRenderException('قالب حاوی محتوای HTML قابل رندر نیست.');
        }

        $css = trim((string) ($template->css ?? ''));

        if ($css !== '') {
            $html = $this->injectCss($html, $this->sanitizer->sanitize($css));
        }

        $headerHtml = $this->renderFragment($template->header_html, $preparedData, $locale);
        $footerHtml = $this->renderFragment($template->footer_html, $preparedData, $locale);

        $resolved = $this->assets->resolveSources([
            'html' => $html,
            'header' => $headerHtml,
            'footer' => $footerHtml,
        ]);

        $assets = array_merge($extraAssets, $resolved['assets']);

        return new RenderedDocument(
            html: $resolved['sources']['html'],
            headerHtml: $resolved['sources']['header'] !== '' ? $resolved['sources']['header'] : null,
            footerHtml: $resolved['sources']['footer'] !== '' ? $resolved['sources']['footer'] : null,
            assets: $assets,
            filename: $this->filename($template),
            pdfOptions: PdfOptions::fromTemplate($template, $options),
        );
    }

    private function renderFragment(?string $fragment, array $data, string $locale): ?string
    {
        if ($fragment === null || trim($fragment) === '') {
            return null;
        }

        $rendered = trim($this->sanitizer->sanitize($this->renderer->render($fragment, $data, $locale)));

        return $rendered === '' ? null : $rendered;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function resolveImageVariables(Template $template, array $data): array
    {
        $template->loadMissing('variables');
        $extraAssets = [];

        foreach ($template->variables as $variable) {
            if ($variable->type !== TemplateVariableType::Image) {
                continue;
            }

            $name = $variable->name;

            if (! array_key_exists($name, $data) || ! is_string($data[$name])) {
                continue;
            }

            $value = trim($data[$name]);

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'asset://')) {
                $asset = $this->assets->resolveHandle(substr($value, strlen('asset://')));
                $data[$name] = $asset['filename'];
                $extraAssets[$asset['filename']] = $asset['contents'];

                continue;
            }

            if (preg_match('#^https?://#i', $value) === 1) {
                $data[$name] = $this->urlGuard->fetchImageAsDataUri($value);
            }

            // data: URIs and relative file names are passed through; the
            // sanitizer still strips data:text/html and javascript: payloads.
        }

        return [$data, $extraAssets];
    }

    private function injectCss(string $html, string $css): string
    {
        if (trim($css) === '') {
            return $html;
        }

        $styleTag = "<style>\n".$css."\n</style>";

        $headPosition = stripos($html, '</head>');

        if ($headPosition !== false) {
            return substr($html, 0, $headPosition).$styleTag.substr($html, $headPosition);
        }

        $bodyPosition = stripos($html, '<body');

        if ($bodyPosition !== false) {
            return substr($html, 0, $bodyPosition).$styleTag.substr($html, $bodyPosition);
        }

        return $styleTag.$html;
    }

    private function filename(Template $template): string
    {
        $slug = trim((string) $template->slug);

        if ($slug === '') {
            $slug = 'document';
        }

        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $slug) ?: 'document';

        return trim($slug, '-').'.pdf';
    }
}
