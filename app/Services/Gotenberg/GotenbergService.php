<?php

declare(strict_types=1);

namespace App\Services\Gotenberg;

use App\Exceptions\GotenbergException;
use App\Services\Documents\Dto\GotenbergResult;
use App\Services\Documents\Dto\PdfOptions;
use App\Services\Documents\Dto\RenderedDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin, defensive client for the internal Gotenberg Chromium service.
 *
 * The service is reached over loopback only and needs no credentials. We never
 * log HTML, PDF contents, template data or API keys — only counts, statuses
 * and durations.
 */
final class GotenbergService
{
    /**
     * Convert a rendered document to a PDF via Gotenberg.
     *
     * @throws GotenbergException
     */
    public function generate(RenderedDocument $document, PdfOptions $options): GotenbergResult
    {
        $url = $this->convertUrl();
        $timeout = max(1, (int) config('pdf.gotenberg.timeout', 90));
        $connectTimeout = max(1, (int) config('pdf.gotenberg.connect_timeout', 10));
        $attempts = max(1, 1 + (int) config('pdf.gotenberg.retry_times', 1));
        $sleepMilliseconds = max(0, (int) config('pdf.gotenberg.retry_sleep_ms', 500));
        $startedAt = microtime(true);
        $lastResponseFailure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->send($url, $document, $options, $timeout, $connectTimeout);
            } catch (ConnectionException $exception) {
                $mapped = $this->mapConnectionException($exception);

                if ($attempt < $attempts) {
                    $this->sleep($sleepMilliseconds);

                    continue;
                }

                $this->logFailure($mapped, null, $startedAt, $attempt);

                throw $mapped;
            }

            $status = $response->status();

            if ($response->successful()) {
                $contents = $response->body();

                if (! str_starts_with($contents, '%PDF')) {
                    $exception = new GotenbergException(
                        GotenbergException::CODE_ERROR,
                        502,
                        'پاسخ سرویس تولید PDF یک فایل PDF معتبر نبود.',
                    );
                    $this->logFailure($exception, $status, $startedAt, $attempt);

                    throw $exception;
                }

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                Log::info('Gotenberg PDF generated', [
                    'duration_ms' => $durationMs,
                    'size' => strlen($contents),
                    'assets' => $document->assetsCount(),
                    'format' => $options->format,
                    'landscape' => $options->landscape,
                ]);

                return new GotenbergResult(
                    contents: $contents,
                    durationMs: $durationMs,
                    statusCode: $status,
                    headers: $response->headers(),
                );
            }

            $mapped = $this->mapStatusFailure($status);
            $lastResponseFailure = $mapped;

            if ($status >= 500 && $attempt < $attempts) {
                $this->sleep($sleepMilliseconds);

                continue;
            }

            $this->logFailure($mapped, $status, $startedAt, $attempt);

            throw $mapped;
        }

        $exception = $lastResponseFailure ?? GotenbergException::unavailable();
        $this->logFailure($exception, null, $startedAt, $attempts);

        throw $exception;
    }

    /**
     * Health probe that never throws; returns a structured summary instead.
     *
     * @return array{ok: bool, status: string, url: string, http_status: int|null, response_time_ms: int, error: string|null, checked_at: string}
     */
    public function health(): array
    {
        $url = $this->healthUrl();
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(max(1, (int) config('pdf.gotenberg.health_timeout', 5)))
                ->connectTimeout(max(1, (int) config('pdf.gotenberg.health_connect_timeout', 2)))
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                ])
                ->get($url);

            $ok = $response->successful();
            $result = [
                'ok' => $ok,
                'status' => $ok ? 'up' : 'down',
                'url' => $url,
                'http_status' => $response->status(),
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $ok ? null : 'پاسخ ناموفق از سرویس Gotenberg',
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable) {
            $result = [
                'ok' => false,
                'status' => 'down',
                'url' => $url,
                'http_status' => null,
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => 'اتصال به سرویس Gotenberg برقرار نشد.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if (! $result['ok']) {
            Log::warning('Gotenberg health check failed', [
                'http_status' => $result['http_status'],
                'response_time_ms' => $result['response_time_ms'],
            ]);
        }

        return $result;
    }

    private function send(
        string $url,
        RenderedDocument $document,
        PdfOptions $options,
        int $timeout,
        int $connectTimeout,
    ): Response {
        $request = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withOptions([
                'allow_redirects' => false,
                'http_errors' => false,
            ])
            ->withHeaders(['Accept' => 'application/pdf'])
            ->attach('files', $document->html, 'index.html', ['Content-Type' => 'text/html; charset=utf-8']);

        if ($document->headerHtml !== null) {
            $request = $request->attach('files', $document->headerHtml, 'header.html', ['Content-Type' => 'text/html; charset=utf-8']);
        }

        if ($document->footerHtml !== null) {
            $request = $request->attach('files', $document->footerHtml, 'footer.html', ['Content-Type' => 'text/html; charset=utf-8']);
        }

        foreach ($document->assets as $filename => $contents) {
            $request = $request->attach('files', $contents, $filename);
        }

        return $request->post($url, $options->toGotenbergFields());
    }

    private function convertUrl(): string
    {
        $base = rtrim((string) config('pdf.gotenberg.url', 'http://127.0.0.1:3000'), '/');
        $path = (string) config('pdf.gotenberg.convert_path', '/forms/chromium/convert/html');

        return $base.'/'.ltrim($path, '/');
    }

    private function healthUrl(): string
    {
        $base = rtrim((string) config('pdf.gotenberg.url', 'http://127.0.0.1:3000'), '/');
        $path = (string) config('pdf.gotenberg.health_path', '/health');

        return $base.'/'.ltrim($path, '/');
    }

    private function mapConnectionException(ConnectionException $exception): GotenbergException
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'error 28')) {
            return GotenbergException::timeout($exception);
        }

        return GotenbergException::unavailable($exception);
    }

    private function mapStatusFailure(int $status): GotenbergException
    {
        if (in_array($status, [408, 504], true)) {
            return GotenbergException::timeout();
        }

        return GotenbergException::error($status);
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function logFailure(GotenbergException $exception, ?int $httpStatus, float $startedAt, int $attempt): void
    {
        Log::warning('Gotenberg PDF generation failed', [
            'error_code' => $exception->getErrorCode(),
            'http_status' => $httpStatus,
            'attempt' => $attempt,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
