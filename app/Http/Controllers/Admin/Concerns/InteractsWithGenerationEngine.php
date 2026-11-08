<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\DocumentStatus;
use App\Models\Asset;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Bridge between the admin panel and the optional document generation engine
 * (owned by the pdf-engine module). Every interaction degrades gracefully when
 * a service class is not installed yet, so the panel never returns a 500.
 */
trait InteractsWithGenerationEngine
{
    protected const GENERATION_SERVICE = 'App\\Services\\Documents\\DocumentGenerationService';

    protected const TEMPLATE_RENDER_SERVICE = 'App\\Services\\Templates\\TemplateRenderService';

    protected const GOTENBERG_SERVICE = 'App\\Services\\Gotenberg\\GotenbergService';

    protected const DOCUMENT_STORAGE_SERVICE = 'App\\Services\\Documents\\DocumentStorageService';

    protected const GENERATE_JOB = 'App\\Jobs\\GenerateDocumentJob';

    protected function generationService(): ?object
    {
        $class = static::GENERATION_SERVICE;

        return class_exists($class) ? app($class) : null;
    }

    protected function templateRenderService(): ?object
    {
        $class = static::TEMPLATE_RENDER_SERVICE;

        return class_exists($class) ? app($class) : null;
    }

    /**
     * Normalised Gotenberg health information.
     *
     * @return array{available: bool, message: string, details: array<string, mixed>}
     */
    protected function gotenbergHealth(): array
    {
        $class = static::GOTENBERG_SERVICE;

        if (class_exists($class)) {
            try {
                $service = app($class);

                if (method_exists($service, 'health')) {
                    $health = $service->health();
                    $health = is_array($health) ? $health : [];

                    $available = (bool) ($health['available'] ?? $health['ok'] ?? false);
                    $status = strtolower((string) ($health['status'] ?? ''));

                    if (! $available && in_array($status, ['ok', 'up', 'healthy', 'ready'], true)) {
                        $available = true;
                    }

                    return [
                        'available' => $available,
                        'message' => $available ? 'Gotenberg در دسترس است.' : 'Gotenberg در دسترس نیست.',
                        'details' => $health,
                    ];
                }
            } catch (Throwable $exception) {
                return [
                    'available' => false,
                    'message' => 'بررسی سلامت Gotenberg ناموفق بود: '.$exception->getMessage(),
                    'details' => [],
                ];
            }
        }

        // Fallback: direct, short-timeout health probe.
        try {
            $url = rtrim((string) config('pdf.gotenberg.url'), '/')
                .(string) config('pdf.gotenberg.health_path', '/health');

            $response = Http::timeout(3)->connectTimeout(2)->get($url);
            $body = $response->json();

            return [
                'available' => $response->successful(),
                'message' => $response->successful()
                    ? 'Gotenberg در دسترس است.'
                    : 'Gotenberg پاسخ ناموفق داد (HTTP '.$response->status().').',
                'details' => is_array($body) ? $body : [],
            ];
        } catch (Throwable $exception) {
            return [
                'available' => false,
                'message' => 'اتصال به Gotenberg برقرار نشد.',
                'details' => [],
            ];
        }
    }

    /**
     * Build the effective preview payload: incoming data merged over defaults.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeVariableDefaults(Template $template, array $data): array
    {
        $template->loadMissing('variables');

        foreach ($template->variables as $variable) {
            /** @var TemplateVariable $variable */
            if (array_key_exists($variable->name, $data)) {
                continue;
            }

            if ($variable->default_value !== null && $variable->default_value !== '') {
                $data[$variable->name] = $variable->defaultCasted();
            }
        }

        return $data;
    }

    /**
     * Render a template HTML for preview.
     *
     * @param  array<string, mixed>  $data
     * @return array{html: string|null, warning: string|null}
     */
    protected function renderPreviewHtml(Template $template, array $data): array
    {
        $html = null;
        $renderedAssets = [];

        $service = $this->generationService();

        if ($service !== null && method_exists($service, 'renderHtml')) {
            try {
                $rendered = $service->renderHtml($template, $this->mergeVariableDefaults($template, $data));
                $html = is_object($rendered) && isset($rendered->html)
                    ? (string) $rendered->html
                    : (is_string($rendered) ? $rendered : null);

                if (is_object($rendered) && isset($rendered->assets) && is_array($rendered->assets)) {
                    $renderedAssets = $rendered->assets;
                }
            } catch (Throwable $exception) {
                return [
                    'html' => null,
                    'warning' => 'رندر قالب ناموفق بود: '.$exception->getMessage(),
                ];
            }
        }

        if ($html === null) {
            $renderer = $this->templateRenderService();

            if ($renderer !== null && method_exists($renderer, 'render')) {
                try {
                    $html = (string) $renderer->render(
                        (string) $template->html,
                        $this->mergeVariableDefaults($template, $data),
                        (string) ($template->locale ?? 'fa'),
                    );
                } catch (Throwable $exception) {
                    return [
                        'html' => null,
                        'warning' => 'رندر قالب ناموفق بود: '.$exception->getMessage(),
                    ];
                }
            }
        }

        if ($html === null) {
            return [
                'html' => null,
                'warning' => 'موتور رندر قالب در این نصب در دسترس نیست. پس از استقرار سرویس تولید سند، پیش‌نمایش HTML فعال می‌شود.',
            ];
        }

        $html = $this->appendTemplateCss($template, $html);

        if ($renderedAssets !== []) {
            $html = $this->inlineRenderedAssets($html, $renderedAssets);
        }

        $html = $this->rewriteAssetUrls($html);

        return ['html' => $html, 'warning' => null];
    }

    /**
     * Inline engine-provided asset files as data URIs so browser previews show
     * the correct fonts and images.
     *
     * @param  array<string, mixed>  $assets
     */
    protected function inlineRenderedAssets(string $html, array $assets): string
    {
        foreach ($assets as $filename => $contents) {
            if (! is_string($filename) || $filename === '' || ! is_string($contents)) {
                continue;
            }

            $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
                'woff2' => 'font/woff2',
                'woff' => 'font/woff',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
                'css' => 'text/css',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };

            $html = str_replace(
                $filename,
                'data:'.$mime.';base64,'.base64_encode($contents),
                $html,
            );
        }

        return $html;
    }

    /**
     * Render a template to PDF for preview.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     * @return array{contents: string, duration_ms: int, size: int}
     *
     * @throws RuntimeException
     */
    protected function renderPreviewPdf(Template $template, array $data, array $options = []): array
    {
        $service = $this->generationService();

        if ($service === null || ! method_exists($service, 'preview')) {
            throw new RuntimeException('موتور تولید PDF در این نصب در دسترس نیست.');
        }

        // Engine accepts snake_case PDF options that override template defaults.
        $effectiveOptions = array_merge(
            $template->pdfOptionsArray(),
            [
                'format' => $template->page_size?->value,
                'orientation' => $template->orientation?->value,
                'margins' => $template->marginsArray(),
            ],
            $options,
        );

        try {
            $result = $service->preview($template, $this->mergeVariableDefaults($template, $data), $effectiveOptions);
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if (! is_array($result) || ! isset($result['contents'])) {
            throw new RuntimeException('خروجی موتور تولید PDF نامعتبر بود.');
        }

        return [
            'contents' => (string) $result['contents'],
            'duration_ms' => (int) ($result['duration_ms'] ?? 0),
            'size' => (int) ($result['size'] ?? strlen((string) $result['contents'])),
        ];
    }

    protected function appendTemplateCss(Template $template, string $html): string
    {
        $css = trim((string) $template->css);

        if ($css === '' || str_contains($html, $css)) {
            return $html;
        }

        $style = '<style>'.$css.'</style>';

        if (stripos($html, '</head>') !== false) {
            return (string) preg_replace('/<\/head>/i', $style.'</head>', $html, 1);
        }

        return $style.$html;
    }

    /**
     * Replace `asset://handle` references with authenticated panel URLs so the
     * preview renders fonts and images.
     */
    protected function rewriteAssetUrls(string $html): string
    {
        return (string) preg_replace_callback(
            '/asset:\/\/([A-Za-z0-9_\-]+)/',
            static function (array $matches): string {
                $asset = Asset::query()->where('handle', $matches[1])->first();

                return $asset instanceof Asset
                    ? route('admin.assets.file', ['asset' => $asset->getKey()])
                    : $matches[0];
            },
            $html,
        );
    }

    /**
     * Stream a generated document back to the browser.
     *
     * @throws RuntimeException
     */
    protected function documentDownloadResponse(GeneratedDocument $document, bool $inline = false): Response
    {
        if (! $document->canDownload()) {
            throw new RuntimeException('این سند قابل دانلود نیست (تکمیل نشده یا منقضی شده است).');
        }

        $contents = null;

        $class = static::DOCUMENT_STORAGE_SERVICE;

        if (class_exists($class)) {
            $storage = app($class);

            if (method_exists($storage, 'exists') && ! $storage->exists($document)) {
                throw new RuntimeException('فایل سند روی دیسک یافت نشد.');
            }

            if (method_exists($storage, 'read')) {
                $contents = $storage->read($document);
            }
        }

        if ($contents === null) {
            $disk = Storage::disk((string) config('pdf.disk', 'local'));
            $path = (string) $document->file_path;

            if ($path === '' || ! $disk->exists($path)) {
                throw new RuntimeException('فایل سند روی دیسک یافت نشد.');
            }

            $contents = $disk->get($path);
        }

        $filename = $document->downloadFileName();
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"';

        return response((string) $contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen((string) $contents),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Retry generation of a failed document.
     *
     * @throws RuntimeException
     */
    protected function retryDocument(GeneratedDocument $document): void
    {
        $service = $this->generationService();

        if ($service !== null && method_exists($service, 'retry')) {
            try {
                $service->retry($document);

                return;
            } catch (Throwable $exception) {
                throw new RuntimeException($exception->getMessage(), 0, $exception);
            }
        }

        $jobClass = static::GENERATE_JOB;

        if (class_exists($jobClass)) {
            $document->forceFill([
                'status' => DocumentStatus::Pending,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            try {
                dispatch(new $jobClass($document));
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'ارسال Job تولید سند ناموفق بود: '.$exception->getMessage(),
                    0,
                    $exception,
                );
            }

            return;
        }

        throw new RuntimeException('موتور تولید سند در این نصب در دسترس نیست؛ امکان تلاش مجدد وجود ندارد.');
    }

    /**
     * Redirect back with a Persian error flash message.
     */
    protected function errorBack(string $message): RedirectResponse
    {
        return back()->with('error', $message);
    }
}
