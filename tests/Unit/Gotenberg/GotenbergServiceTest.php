<?php

declare(strict_types=1);

namespace Tests\Unit\Gotenberg;

use App\Exceptions\GotenbergException;
use App\Services\Documents\Dto\PdfOptions;
use App\Services\Documents\Dto\RenderedDocument;
use App\Services\Gotenberg\GotenbergService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\TestCase;

class GotenbergServiceTest extends TestCase
{
    use FakePdf;

    private GotenbergService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pdf.gotenberg.url' => 'http://127.0.0.1:3000',
            'pdf.gotenberg.timeout' => 30,
            'pdf.gotenberg.connect_timeout' => 5,
            'pdf.gotenberg.retry_times' => 0,
            'pdf.gotenberg.retry_sleep_ms' => 0,
        ]);

        $this->service = $this->app->make(GotenbergService::class);
    }

    public function test_generate_posts_multipart_to_the_convert_endpoint(): void
    {
        $pdf = self::mockPdfBytes('MARKER-A');

        Http::fake([
            'http://127.0.0.1:3000/forms/chromium/convert/html' => Http::response($pdf, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $result = $this->service->generate($this->makeDocument(), $this->makePdfOptions());

        $this->assertSame($pdf, $result->contents);
        $this->assertSame(strlen($pdf), $result->size());
        $this->assertSame(200, $result->statusCode);
        $this->assertGreaterThanOrEqual(0, $result->durationMs);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame('http://127.0.0.1:3000/forms/chromium/convert/html', $request->url());
            $this->assertTrue($request->isMultipart());
            $this->assertSame('application/pdf', $request->header('Accept')[0]);

            $fields = $this->fields($request);

            $this->assertSame('8.2677', $fields['paperWidth']);
            $this->assertSame('11.6929', $fields['paperHeight']);
            $this->assertSame('0.4724', $fields['marginTop']);
            $this->assertSame('0.7874', $fields['marginRight']);
            $this->assertSame('0.5906', $fields['marginBottom']);
            $this->assertSame('0.3937', $fields['marginLeft']);
            $this->assertSame('true', $fields['printBackground']);
            $this->assertSame('1', $fields['scale']);
            $this->assertSame('false', $fields['landscape']);
            $this->assertSame('true', $fields['preferCssPageSize']);
            $this->assertSame('true', $fields['displayHeaderFooter']);

            $files = $this->files($request);
            $indexHtml = collect($files)->firstWhere('filename', 'index.html');

            $this->assertNotNull($indexHtml);
            $this->assertStringContainsString('فاکتور فارسی', $indexHtml['contents']);
            $this->assertStringContainsString('page-break-after', $indexHtml['contents']);
            $this->assertNotNull(collect($files)->firstWhere('filename', 'vazirmatn-regular.woff2'));
            $this->assertNotNull(collect($files)->firstWhere('filename', 'header.html'));
            $this->assertNotNull(collect($files)->firstWhere('filename', 'footer.html'));

            return true;
        });
    }

    public function test_generate_uses_configured_base_url_and_convert_path(): void
    {
        config([
            'pdf.gotenberg.url' => 'http://gotenberg.local:3030/',
            'pdf.gotenberg.convert_path' => '/custom/convert',
        ]);

        Http::fake(['*' => Http::response(self::mockPdfBytes(), 200)]);

        $this->service->generate($this->makeDocument(), $this->makePdfOptions());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gotenberg.local:3030/custom/convert');
    }

    public function test_server_error_maps_to_gotenberg_error(): void
    {
        Http::fake(['*' => Http::response('internal error page', 500)]);

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_ERROR, $exception->getErrorCode());
            $this->assertSame(502, $exception->getStatusCode());
            $this->assertStringContainsString('500', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_timeout_http_status_maps_to_gotenberg_timeout(): void
    {
        Http::fake(['*' => Http::response('', 504)]);

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_TIMEOUT, $exception->getErrorCode());
            $this->assertSame(504, $exception->getStatusCode());
        }
    }

    public function test_connection_refused_maps_to_gotenberg_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_UNAVAILABLE, $exception->getErrorCode());
            $this->assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_connection_timeout_maps_to_gotenberg_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds'));

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_TIMEOUT, $exception->getErrorCode());
            $this->assertSame(504, $exception->getStatusCode());
        }
    }

    public function test_transient_failures_are_retried(): void
    {
        config(['pdf.gotenberg.retry_times' => 1, 'pdf.gotenberg.retry_sleep_ms' => 0]);

        Http::fakeSequence()
            ->push('error', 500)
            ->push(self::mockPdfBytes('AFTER-RETRY'), 200);

        $result = $this->service->generate($this->makeDocument(), $this->makePdfOptions());

        $this->assertStringContainsString('AFTER-RETRY', $result->contents);
        Http::assertSentCount(2);
    }

    public function test_non_pdf_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response('<html>not a pdf</html>', 200, ['Content-Type' => 'text/html'])]);

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_ERROR, $exception->getErrorCode());
            $this->assertSame(502, $exception->getStatusCode());
        }
    }

    public function test_failures_do_not_log_html_or_payload_content(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response('boom', 503)]);

        try {
            $this->service->generate($this->makeDocument(), $this->makePdfOptions());
        } catch (GotenbergException) {
            // expected
        }

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []): bool {
            $encoded = (string) json_encode($context, JSON_UNESCAPED_UNICODE);

            return ! str_contains($encoded, 'فاکتور فارسی')
                && ! str_contains($encoded, '<html')
                && ! isset($context['html'])
                && ! isset($context['payload']);
        });
    }

    public function test_health_returns_structured_ok_result(): void
    {
        Http::fake(['http://127.0.0.1:3000/health' => Http::response('OK', 200)]);

        $health = $this->service->health();

        $this->assertTrue($health['ok']);
        $this->assertSame('up', $health['status']);
        $this->assertSame('http://127.0.0.1:3000/health', $health['url']);
        $this->assertSame(200, $health['http_status']);
        $this->assertNull($health['error']);
        $this->assertIsInt($health['response_time_ms']);
    }

    public function test_health_reports_down_on_server_error(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $health = $this->service->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('down', $health['status']);
        $this->assertSame(500, $health['http_status']);
    }

    public function test_health_never_throws_on_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $health = $this->service->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('down', $health['status']);
        $this->assertNull($health['http_status']);
        $this->assertNotNull($health['error']);
    }

    public function test_header_footer_files_are_only_sent_when_present(): void
    {
        Http::fake(['*' => Http::response(self::mockPdfBytes(), 200)]);

        $document = new RenderedDocument(
            html: '<html><body>no header</body></html>',
            headerHtml: null,
            footerHtml: null,
            assets: [],
            filename: 'plain.pdf',
            pdfOptions: $this->makePdfOptions(),
        );

        $this->service->generate($document, $this->makePdfOptions());

        Http::assertSent(function (Request $request): bool {
            $files = $this->files($request);
            $this->assertCount(1, $files);
            $this->assertSame('index.html', $files[0]['filename']);

            return true;
        });
    }

    private function makeDocument(): RenderedDocument
    {
        return new RenderedDocument(
            html: '<!DOCTYPE html><html lang="fa" dir="rtl"><head><style>.page{page-break-after: always;}</style></head>'
                .'<body><h1>فاکتور فارسی</h1><img src="logo.png"></body></html>',
            headerHtml: '<header>سرصفحه</header>',
            footerHtml: '<footer>پاصفحه</footer>',
            assets: [
                'vazirmatn-regular.woff2' => "\x00FONT\x00",
                'logo.png' => "\x89PNG\r\n\x1a\nLOGO",
            ],
            filename: 'invoice-standard.pdf',
            pdfOptions: $this->makePdfOptions(),
        );
    }

    private function makePdfOptions(): PdfOptions
    {
        return new PdfOptions(
            format: 'A4',
            paperWidthInches: 8.267716535,
            paperHeightInches: 11.692913386,
            marginTopInches: 0.472440945,
            marginRightInches: 0.787401575,
            marginBottomInches: 0.590551181,
            marginLeftInches: 0.393700787,
            printBackground: true,
            scale: 1.0,
            landscape: false,
            preferCssPageSize: true,
            displayHeaderFooter: true,
        );
    }

    /**
     * @return array<string, string>
     */
    private function fields(Request $request): array
    {
        $fields = [];

        foreach ($request->data() as $part) {
            if (is_array($part) && isset($part['name'], $part['contents']) && ! isset($part['filename'])) {
                $fields[(string) $part['name']] = (string) $part['contents'];
            }
        }

        return $fields;
    }

    /**
     * @return list<array{name: string, contents: string, filename: string}>
     */
    private function files(Request $request): array
    {
        $files = [];

        foreach ($request->data() as $part) {
            if (is_array($part) && isset($part['name'], $part['contents'], $part['filename'])) {
                $files[] = [
                    'name' => (string) $part['name'],
                    'contents' => (string) $part['contents'],
                    'filename' => (string) $part['filename'],
                ];
            }
        }

        return $files;
    }
}
