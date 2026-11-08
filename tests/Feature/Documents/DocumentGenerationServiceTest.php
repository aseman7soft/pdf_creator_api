<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use App\Enums\JobStatus;
use App\Events\DocumentGenerated;
use App\Events\DocumentGenerationFailed;
use App\Exceptions\DocumentGenerationException;
use App\Exceptions\GotenbergException;
use App\Jobs\GenerateDocumentJob;
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\User;
use App\Services\Documents\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\Feature\Documents\Support\MultipartAssertions;
use Tests\TestCase;

class DocumentGenerationServiceTest extends TestCase
{
    use FakePdf;
    use MultipartAssertions;
    use RefreshDatabase;

    private DocumentGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'pdf.gotenberg.url' => 'http://127.0.0.1:3000',
            'pdf.gotenberg.retry_times' => 0,
            'pdf.gotenberg.retry_sleep_ms' => 0,
        ]);

        $this->service = $this->app->make(DocumentGenerationService::class);
    }

    public function test_generate_sync_completes_document_and_stores_private_pdf(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();
        $project = ApiProject::factory()->create();
        $apiKey = ApiKey::factory()->create(['api_project_id' => $project->getKey()]);
        $user = User::factory()->create();

        Event::fake([DocumentGenerated::class]);

        $document = $this->service->generateSync(
            $template,
            $this->hotelData(),
            ['format' => 'A4'],
            $project,
            $apiKey,
            'REF-1',
            $user,
        );

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertSame(GenerationMode::Sync, $document->generation_mode);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($template->name, $document->template_name);
        $this->assertSame(1, $document->template_version);
        $this->assertSame($project->getKey(), $document->api_project_id);
        $this->assertSame($apiKey->getKey(), $document->api_key_id);
        $this->assertSame('REF-1', $document->reference);
        $this->assertNotNull($document->generated_at);
        $this->assertTrue($document->expires_at->isFuture());

        $this->assertMatchesRegularExpression(
            '#^documents/\d{4}/\d{2}/'.preg_quote($document->ulid, '#').'\.pdf$#',
            (string) $document->file_path,
        );
        $this->assertSame(strlen(self::mockPdfBytes('SYNC-PDF')), $document->file_size);
        $this->assertSame(self::mockPdfBytes('SYNC-PDF'), Storage::disk('local')->get((string) $document->file_path));
        $this->assertFalse(Storage::disk('public')->exists((string) $document->file_path));

        $this->assertIsArray($document->pdf_meta);
        $this->assertArrayHasKey('duration_ms', $document->pdf_meta);
        $this->assertSame($document->file_size, $document->pdf_meta['size']);
        $this->assertArrayHasKey('gotenberg_ms', $document->pdf_meta);

        $this->assertSame('علی رضایی', $document->payload['guest_name']);
        $this->assertSame(1, $document->payload['room_count']);

        Event::assertDispatched(DocumentGenerated::class, fn (DocumentGenerated $event): bool => $event->document->getKey() === $document->getKey());
    }

    public function test_generate_sync_forwards_persian_and_multipage_html_to_gotenberg(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();

        $this->service->generateSync($template, $this->hotelData(['guest_name' => 'علی <script>alert(1)</script>']));

        Http::assertSent(function (Request $request): bool {
            $html = $this->multipartFileContents($request, 'index.html');

            $this->assertNotNull($html);
            $this->assertStringContainsString('واچر رزرو هتل', $html);
            $this->assertStringContainsString('page-break-after', $html);
            $this->assertStringContainsString('فاکتور فارسی', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

            $fields = $this->multipartFields($request);
            $this->assertSame('true', $fields['printBackground']);

            return true;
        });
    }

    public function test_generate_sync_maps_gotenberg_failure_to_failed_document(): void
    {
        Http::fake(['*' => Http::response('internal error page', 500)]);

        $template = $this->makeHotelTemplate();

        try {
            $this->service->generateSync($template, $this->hotelData());
            $this->fail('Expected a GotenbergException.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_ERROR, $exception->getErrorCode());
            $this->assertSame(502, $exception->getStatusCode());
            $this->assertStringNotContainsString('internal error page', $exception->getMessage());
        }

        $document = GeneratedDocument::query()->latest('id')->firstOrFail();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('GOTENBERG_ERROR', $document->error_code);
        $this->assertNotEmpty($document->error_message);
        $this->assertNull($document->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_generate_sync_dispatch_failure_event(): void
    {
        Event::fake([DocumentGenerationFailed::class]);
        Http::fake(['*' => Http::response('', 503)]);

        $template = $this->makeHotelTemplate();

        try {
            $this->service->generateSync($template, $this->hotelData());
        } catch (GotenbergException) {
            // expected
        }

        Event::assertDispatched(
            DocumentGenerationFailed::class,
            fn (DocumentGenerationFailed $event): bool => $event->errorCode === GotenbergException::CODE_ERROR,
        );
    }

    public function test_generate_sync_rejects_inactive_template_without_creating_document(): void
    {
        $template = $this->makeHotelTemplate(['is_active' => false]);

        try {
            $this->service->generateSync($template, $this->hotelData());
            $this->fail('Expected a DocumentGenerationException.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('TEMPLATE_INACTIVE', $exception->getErrorCode());
        }

        $this->assertSame(0, GeneratedDocument::query()->count());
        Http::assertNothingSent();
    }

    public function test_generate_sync_validation_error_does_not_create_document(): void
    {
        $template = $this->makeHotelTemplate();

        try {
            $this->service->generateSync($template, ['hotel_name' => 'هتل']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.guest_name', $exception->errors());
        }

        $this->assertSame(0, GeneratedDocument::query()->count());
        Http::assertNothingSent();
    }

    public function test_generate_sync_uses_template_defaults_for_missing_optional_values(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();

        $document = $this->service->generateSync($template, $this->hotelData());

        $this->assertSame(1, $document->payload['room_count']);
        $this->assertSame('IRR', $document->payload['currency']);
    }

    public function test_preview_returns_pdf_without_persisting_anything(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();

        $result = $this->service->preview($template, $this->hotelData());

        $this->assertStringStartsWith('%PDF-1.4', $result['contents']);
        $this->assertSame(strlen($result['contents']), $result['size']);
        $this->assertIsInt($result['duration_ms']);
        $this->assertSame(0, GeneratedDocument::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_render_html_returns_rendered_document_without_calling_gotenberg(): void
    {
        $template = $this->makeHotelTemplate();

        $rendered = $this->service->renderHtml($template, $this->hotelData());

        $this->assertStringContainsString('علی رضایی', $rendered->html);
        $this->assertStringContainsString('واچر رزرو هتل', $rendered->html);
        $this->assertSame('A4', $rendered->pdfOptions->format);
        Http::assertNothingSent();
    }

    public function test_queue_creates_pending_document_and_document_job(): void
    {
        Queue::fake();
        $template = $this->makeHotelTemplate();

        $document = $this->service->queue($template, $this->hotelData(), [], null, null, 'QUEUED-1');

        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertSame(GenerationMode::Async, $document->generation_mode);
        $this->assertSame('QUEUED-1', $document->reference);
        $this->assertSame('علی رضایی', $document->payload['guest_name']);

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();

        $this->assertSame(JobStatus::Pending, $jobRow->status);
        $this->assertSame(0, $jobRow->attempts);
        $this->assertSame(3, $jobRow->max_attempts);
        $this->assertSame('documents', $jobRow->queue);

        Queue::assertPushed(GenerateDocumentJob::class, fn (GenerateDocumentJob $job): bool => $job->documentId === $document->getKey()
            && $job->queue === 'documents');

        Http::assertNothingSent();
        $this->assertNull($document->file_path);
    }

    public function test_queue_validation_error_does_not_create_document(): void
    {
        Queue::fake();
        $template = $this->makeHotelTemplate();

        try {
            $this->service->queue($template, ['hotel_name' => 'هتل']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, GeneratedDocument::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_retry_failed_document_dispatches_a_new_job(): void
    {
        Queue::fake();
        $template = $this->makeHotelTemplate();

        $document = GeneratedDocument::factory()->failed()->create([
            'template_id' => $template->getKey(),
            'payload' => $this->hotelData(),
        ]);

        $this->service->retry($document);

        $document->refresh();

        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNull($document->error_code);
        $this->assertNull($document->error_message);

        $jobs = DocumentJob::query()->where('generated_document_id', $document->getKey())->get();
        $this->assertCount(1, $jobs);
        $this->assertSame(JobStatus::Pending, $jobs->first()->status);

        Queue::assertPushed(GenerateDocumentJob::class, fn (GenerateDocumentJob $job): bool => $job->documentId === $document->getKey());
    }

    public function test_retry_rejects_documents_that_are_not_failed(): void
    {
        $template = $this->makeHotelTemplate();
        $document = GeneratedDocument::factory()->completed()->create(['template_id' => $template->getKey()]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->retry($document);
    }

    public function test_retry_rejects_inactive_template(): void
    {
        $template = $this->makeHotelTemplate(['is_active' => false]);
        $document = GeneratedDocument::factory()->failed()->create(['template_id' => $template->getKey()]);

        try {
            $this->service->retry($document);
            $this->fail('Expected a DocumentGenerationException.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('TEMPLATE_INACTIVE', $exception->getErrorCode());
        }
    }

    public function test_failure_logs_do_not_contain_payload_data(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response('', 500)]);

        $template = $this->makeHotelTemplate();

        try {
            $this->service->generateSync($template, $this->hotelData([
                'guest_name' => 'PII-SENTINEL-NAME',
                'hotel_name' => 'PII-SENTINEL-HOTEL',
            ]));
        } catch (GotenbergException) {
            // expected
        }

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context = []): bool {
            $encoded = (string) json_encode($context, JSON_UNESCAPED_UNICODE);

            return ! str_contains($encoded, 'PII-SENTINEL')
                && ! isset($context['payload'])
                && ! isset($context['html']);
        });
    }

    public function test_cli_options_are_forwarded_to_gotenberg(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();

        $document = $this->service->generateSync($template, $this->hotelData(), [
            'format' => 'A5',
            'orientation' => 'landscape',
            'scale' => 0.9,
        ]);

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertSame('A5', $document->pdf_meta['format']);
        $this->assertSame('landscape', $document->pdf_meta['orientation']);

        Http::assertSent(function (Request $request): bool {
            $fields = $this->multipartFields($request);

            $this->assertSame('true', $fields['landscape']);
            $this->assertSame('0.9', $fields['scale']);
            $this->assertSame('5.8268', $fields['paperWidth']);

            return true;
        });
    }

    public function test_document_payload_is_encrypted_at_rest(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();

        $document = $this->service->generateSync($template, $this->hotelData(['guest_name' => 'SECRET-NAME']));

        $raw = DB::table('generated_documents')
            ->where('id', $document->getKey())
            ->value('payload');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('SECRET-NAME', $raw);
        $this->assertSame('SECRET-NAME', $document->payload['guest_name']);
    }

    public function test_retry_runs_the_job_and_completes_the_document_when_queue_is_sync(): void
    {
        $this->fakeGotenbergSuccess();
        $template = $this->makeHotelTemplate();
        $document = GeneratedDocument::factory()->failed()->create([
            'template_id' => $template->getKey(),
            'payload' => $this->hotelData(),
        ]);

        $this->service->retry($document);

        $document->refresh();

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertNotNull($document->file_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $document->file_path));

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();
        $this->assertSame(JobStatus::Completed, $jobRow->status);
    }

    private function fakeGotenbergSuccess(): void
    {
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response(
                self::mockPdfBytes('SYNC-PDF'),
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeHotelTemplate(array $overrides = []): Template
    {
        $template = Template::factory()->create(array_merge([
            'slug' => 'hotel-voucher',
            'name' => 'واچر هتل',
            'version' => 1,
            'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>واچر رزرو هتل</title>
<style>.page { page-break-after: always; } body { font-family: Vazirmatn, sans-serif; }</style>
</head>
<body>
  <div class="page">
    <h1>{{ hotel_name }}</h1>
    <p>کد رزرو: {{ booking_reference }} | تاریخ: @jdate($check_in)</p>
    <p>فاکتور فارسی — {{ guest_name }}</p>
    <p>{{ room_count }} اتاق — {{ total_price | number }} {{ currency }}</p>
    @if(isset($notes) && $notes)<div class="notes">{{ notes }}</div>@endif
  </div>
</body>
</html>
HTML,
            'css' => '.page { direction: rtl; }',
            'footer_html' => '<footer>صفحه <span class="pageNumber"></span></footer>',
            'is_active' => true,
            'page_size' => 'A4',
            'orientation' => 'portrait',
        ], $overrides));

        $variables = [
            ['name' => 'guest_name', 'type' => 'string', 'is_required' => true],
            ['name' => 'hotel_name', 'type' => 'string', 'is_required' => true],
            ['name' => 'booking_reference', 'type' => 'string', 'is_required' => true],
            ['name' => 'check_in', 'type' => 'date', 'is_required' => true],
            ['name' => 'room_count', 'type' => 'integer', 'is_required' => false, 'default_value' => '1', 'min_value' => 1],
            ['name' => 'total_price', 'type' => 'decimal', 'is_required' => true, 'min_value' => 0],
            ['name' => 'currency', 'type' => 'string', 'is_required' => false, 'default_value' => 'IRR'],
            ['name' => 'notes', 'type' => 'text', 'is_required' => false],
        ];

        foreach ($variables as $index => $variable) {
            $template->variables()->create([
                'name' => $variable['name'],
                'label' => $variable['name'],
                'type' => $variable['type'],
                'is_required' => $variable['is_required'] ?? false,
                'default_value' => $variable['default_value'] ?? null,
                'min_value' => $variable['min_value'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $template->refresh()->load('variables');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function hotelData(array $overrides = []): array
    {
        return array_merge([
            'guest_name' => 'علی رضایی',
            'hotel_name' => 'هتل پارسیان',
            'booking_reference' => 'BK-2026-1',
            'check_in' => '2026-11-08',
            'total_price' => 12500000,
        ], $overrides);
    }
}
