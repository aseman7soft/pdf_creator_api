<?php

declare(strict_types=1);

namespace App\Services\Documents\Dto;

/**
 * Everything the Gotenberg client needs to build one PDF:
 * the index HTML document, optional header/footer documents, flattened
 * binary attachments (fonts/images) and the page options.
 */
final class RenderedDocument
{
    /**
     * @param  array<string, string>  $assets  map of flattened filename => binary contents
     */
    public function __construct(
        public readonly string $html,
        public readonly ?string $headerHtml,
        public readonly ?string $footerHtml,
        public readonly array $assets,
        public readonly string $filename,
        public readonly PdfOptions $pdfOptions,
    ) {}

    public function assetsCount(): int
    {
        return count($this->assets);
    }
}
