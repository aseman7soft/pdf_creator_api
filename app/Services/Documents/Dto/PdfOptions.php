<?php

declare(strict_types=1);

namespace App\Services\Documents\Dto;

use App\Models\Template;

/**
 * Immutable PDF page/print options for a single render.
 *
 * Values are normalised to inches because that is the unit the Gotenberg
 * Chromium API expects; template margins are stored in millimetres.
 */
final class PdfOptions
{
    public const DEFAULT_FORMAT = 'A4';

    private const MM_PER_INCH = 25.4;

    public function __construct(
        public readonly string $format,
        public readonly float $paperWidthInches,
        public readonly float $paperHeightInches,
        public readonly float $marginTopInches,
        public readonly float $marginRightInches,
        public readonly float $marginBottomInches,
        public readonly float $marginLeftInches,
        public readonly bool $printBackground,
        public readonly float $scale,
        public readonly bool $landscape,
        public readonly bool $preferCssPageSize,
        public readonly bool $displayHeaderFooter,
    ) {}

    /**
     * Build options from the template defaults overridden by API/panel options.
     *
     * Supported override keys (snake_case, matching template pdf_options):
     * format|page_size, orientation, margins{top,right,bottom,left} (mm),
     * paper_width, paper_height (mm), print_background, scale,
     * prefer_css_page_size, display_header_footer.
     *
     * @param  array<string, mixed>  $options
     */
    public static function fromTemplate(Template $template, array $options = []): self
    {
        $templateOptions = $template->pdfOptionsArray();
        $format = self::normaliseFormat($options['format'] ?? $options['page_size'] ?? $template->page_size?->value);
        $orientation = strtolower((string) ($options['orientation'] ?? $template->orientation?->value ?? config('pdf.default_orientation', 'portrait')));
        $landscape = $orientation === 'landscape';

        [$widthMm, $heightMm] = self::dimensions($format, $options, $templateOptions);

        $margins = $template->marginsArray();

        if (isset($options['margins']) && is_array($options['margins'])) {
            $margins = array_merge($margins, $options['margins']);
        }

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $key = 'margin_'.$side;

            if (isset($options[$key]) && is_numeric($options[$key])) {
                $margins[$side] = (float) $options[$key];
            }
        }

        $scale = $options['scale'] ?? $templateOptions['scale'] ?? 1.0;
        $scale = is_numeric($scale) ? (float) $scale : 1.0;
        $scale = min(2.0, max(0.1, $scale));

        $printBackground = $options['print_background'] ?? $templateOptions['print_background'] ?? true;
        $preferCssPageSize = $options['prefer_css_page_size'] ?? $templateOptions['prefer_css_page_size'] ?? true;
        $displayHeaderFooter = $options['display_header_footer']
            ?? $templateOptions['display_header_footer']
            ?? ($template->header_html !== null || $template->footer_html !== null);

        return new self(
            format: $format,
            paperWidthInches: self::toInches($widthMm),
            paperHeightInches: self::toInches($heightMm),
            marginTopInches: self::toInches(self::numeric($margins['top'] ?? 0)),
            marginRightInches: self::toInches(self::numeric($margins['right'] ?? 0)),
            marginBottomInches: self::toInches(self::numeric($margins['bottom'] ?? 0)),
            marginLeftInches: self::toInches(self::numeric($margins['left'] ?? 0)),
            printBackground: (bool) $printBackground,
            scale: $scale,
            landscape: $landscape,
            preferCssPageSize: (bool) $preferCssPageSize,
            displayHeaderFooter: (bool) $displayHeaderFooter,
        );
    }

    /**
     * Convert to the multipart field map expected by Gotenberg's
     * POST /forms/chromium/convert/html endpoint.
     *
     * @return array<string, string>
     */
    public function toGotenbergFields(): array
    {
        return [
            'paperWidth' => $this->formatInches($this->paperWidthInches),
            'paperHeight' => $this->formatInches($this->paperHeightInches),
            'marginTop' => $this->formatInches($this->marginTopInches),
            'marginRight' => $this->formatInches($this->marginRightInches),
            'marginBottom' => $this->formatInches($this->marginBottomInches),
            'marginLeft' => $this->formatInches($this->marginLeftInches),
            'printBackground' => $this->printBackground ? 'true' : 'false',
            'scale' => $this->formatInches($this->scale),
            'landscape' => $this->landscape ? 'true' : 'false',
            'preferCssPageSize' => $this->preferCssPageSize ? 'true' : 'false',
            // Gotenberg 9+: omit displayHeaderFooter (unknown form field); header.html/footer.html auto-enable.
        ];
    }

    public function formatInches(float $inches): string
    {
        $formatted = number_format($inches, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private static function normaliseFormat(mixed $format): string
    {
        $formats = array_keys((array) config('pdf.page_formats', []));

        if (is_string($format)) {
            foreach ($formats as $candidate) {
                if (strcasecmp($candidate, $format) === 0) {
                    return $candidate;
                }
            }

            if (strcasecmp($format, 'custom') === 0) {
                return 'Custom';
            }
        }

        $default = (string) config('pdf.default_format', self::DEFAULT_FORMAT);

        foreach ($formats as $candidate) {
            if (strcasecmp($candidate, $default) === 0) {
                return $candidate;
            }
        }

        return self::DEFAULT_FORMAT;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $templateOptions
     * @return array{0: float, 1: float}
     */
    private static function dimensions(string $format, array $options, array $templateOptions): array
    {
        $formats = (array) config('pdf.page_formats', []);

        if ($format === 'Custom') {
            $width = $options['paper_width'] ?? $templateOptions['paper_width'] ?? $templateOptions['width'] ?? null;
            $height = $options['paper_height'] ?? $templateOptions['paper_height'] ?? $templateOptions['height'] ?? null;

            if (is_numeric($width) && is_numeric($height) && (float) $width > 0 && (float) $height > 0) {
                return [(float) $width, (float) $height];
            }

            $format = self::DEFAULT_FORMAT;
        }

        foreach ($formats as $name => $dimensions) {
            if (strcasecmp((string) $name, $format) === 0 && is_array($dimensions)) {
                return [
                    (float) ($dimensions['width'] ?? 210),
                    (float) ($dimensions['height'] ?? 297),
                ];
            }
        }

        return [210.0, 297.0];
    }

    private static function toInches(float $millimetres): float
    {
        return round($millimetres / self::MM_PER_INCH, 6);
    }

    private static function numeric(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
