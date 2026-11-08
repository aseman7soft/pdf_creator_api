<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Events\DocumentGenerated;
use App\Events\DocumentGenerationFailed;
use App\Exceptions\GotenbergException;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Services\Documents\DocumentGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\TestCase;

class GenerateDocumentJobTest extends TestCase
{
    use FakePdf;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'pdf.gotenberg.retry_times' => 0,
            'pdf.gotenberg.retry_sleep_ms' => 0,
            'pdf.queue.tries' => 4,
            'pdf.queue.timeout' => 120,
            'pdf.queue.backoff' => 7,
            'pdf.queue.name' => 'documents',
        ]);
    }

    public function test_job_settings_come_from_config_and_it_is_unique_per_document(): void
    {
        $job = new GenerateDocumentJob(42);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(42, $job->documentId);
        $this->assertSame('42', $job->uniqueId());
        $this->assertSame(4, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(7, $job->backoff);
        $this->assertSame('documents', $job->queue);
        $this->assertGreaterThan(0, $job->uniqueFor);
    }

    public function test_sync_queue_execution_completes_document_and_document_job(): void
    {
        Event::fake([DocumentGenerated::class]);
        Http::fake(['*' => Http::response(self::mockPdfBytes('JOB-OK'), 200)]);

        $template = $this->makeTemplate();
        $service = $this->app->make(DocumentGenerationService::class);

        $document = $service->queue($template, $this->data());

        $document->refresh();

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertNotNull($document->file_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $document->file_path));
        $this->assertSame(self::mockPdfBytes('JOB-OK'), Storage::disk('local')->get((string) $document->file_path));

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();

        $this->assertSame(JobStatus::Completed, $jobRow->status);
        $this->assertSame(1, $jobRow->attempts);
        $this->assertNotNull($jobRow->started_at);
        $this->assertNotNull($jobRow->finished_at);
        $this->assertNotNull($jobRow->duration_ms);
        $this->assertNull($jobRow->error_code);

        Event::assertDispatched(DocumentGenerated::class, fn (DocumentGenerated $event): bool => $event->document->getKey() === $document->getKey());
    }

    public function test_non_retryable_render_error_marks_document_and_job_failed_without_throwing(): void
    {
        Event::fake([DocumentGenerationFailed::class]);
        Http::fake();

        $template = $this->makeTemplate(['html' => '<html><body><img src="asset://missing-asset"></body></html>']);
        $document = $this->queuedDocument($template);

        $job = new GenerateDocumentJob($document->getKey());
        $job->handle($this->app->make(DocumentGenerationService::class));

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('RENDER_ERROR', $document->error_code);
        $this->assertNotEmpty($document->error_message);

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();
        $this->assertSame(JobStatus::Failed, $jobRow->status);
        $this->assertSame('RENDER_ERROR', $jobRow->error_code);

        Event::assertDispatched(DocumentGenerationFailed::class, fn (DocumentGenerationFailed $event): bool => $event->errorCode === 'RENDER_ERROR');
        Http::assertNothingSent();
    }

    public function test_retryable_gotenberg_failure_is_rethrown_for_queue_retry(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $template = $this->makeTemplate();
        $document = $this->queuedDocument($template);

        $job = new GenerateDocumentJob($document->getKey());

        try {
            $job->handle($this->app->make(DocumentGenerationService::class));
            $this->fail('Expected the retryable GotenbergException to be rethrown.');
        } catch (GotenbergException $exception) {
            $this->assertSame(GotenbergException::CODE_ERROR, $exception->getErrorCode());
        }

        $document->refresh();

        // Intermediate failure: the document is not marked failed yet; the
        // queue worker will retry and only the final failure is persisted.
        $this->assertNotSame(DocumentStatus::Failed, $document->status);

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();
        $this->assertSame(JobStatus::Processing, $jobRow->status);
        $this->assertSame(1, $jobRow->attempts);
    }

    public function test_failed_hook_marks_final_failure_and_dispatches_event(): void
    {
        Event::fake([DocumentGenerationFailed::class]);

        $template = $this->makeTemplate();
        $document = $this->queuedDocument($template);

        $job = new GenerateDocumentJob($document->getKey());
        $job->failed(GotenbergException::unavailable());

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('GOTENBERG_UNAVAILABLE', $document->error_code);
        $this->assertNotEmpty($document->error_message);
        $this->assertNotNull($document->generated_at);

        $jobRow = DocumentJob::query()->where('generated_document_id', $document->getKey())->firstOrFail();
        $this->assertSame(JobStatus::Failed, $jobRow->status);
        $this->assertSame('GOTENBERG_UNAVAILABLE', $jobRow->error_code);
        $this->assertNotNull($jobRow->finished_at);

        Event::assertDispatched(
            DocumentGenerationFailed::class,
            fn (DocumentGenerationFailed $event): bool => $event->errorCode === 'GOTENBERG_UNAVAILABLE',
        );
    }

    public function test_failed_hook_masks_unexpected_exceptions_with_generic_persian_message(): void
    {
        $template = $this->makeTemplate();
        $document = $this->queuedDocument($template);

        $job = new GenerateDocumentJob($document->getKey());
        $job->failed(new \RuntimeException('SQLSTATE internal secret details'));

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('RENDER_ERROR', $document->error_code);
        $this->assertStringNotContainsString('SQLSTATE', (string) $document->error_message);
        $this->assertStringNotContainsString('internal secret', (string) $document->error_message);
    }

    public function test_completed_document_is_not_regenerated(): void
    {
        Http::fake();

        $template = $this->makeTemplate();
        $document = GeneratedDocument::factory()->completed()->create([
            'template_id' => $template->getKey(),
            'payload' => $this->data(),
        ]);

        $job = new GenerateDocumentJob($document->getKey());
        $job->handle($this->app->make(DocumentGenerationService::class));

        Http::assertNothingSent();
        $this->assertSame(DocumentStatus::Completed, $document->refresh()->status);
    }

    public function test_missing_document_is_ignored(): void
    {
        Http::fake();

        $job = new GenerateDocumentJob(999999);
        $job->handle($this->app->make(DocumentGenerationService::class));

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTemplate(array $overrides = []): Template
    {
        $template = Template::factory()->create(array_merge([
            'slug' => 'job-template',
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head>'
                .'<body><h1>{{ hotel_name }}</h1><p>{{ guest_name }}</p><p>{{ total_price | number }}</p></body></html>',
            'css' => 'body { direction: rtl; }',
            'is_active' => true,
            'page_size' => 'A4',
            'orientation' => 'portrait',
        ], $overrides));

        foreach ([
            ['name' => 'guest_name', 'type' => 'string', 'required' => true],
            ['name' => 'hotel_name', 'type' => 'string', 'required' => true],
            ['name' => 'total_price', 'type' => 'decimal', 'required' => true, 'min_value' => 0],
        ] as $index => $variable) {
            $template->variables()->create([
                'name' => $variable['name'],
                'label' => $variable['name'],
                'type' => $variable['type'],
                'is_required' => $variable['required'],
                'min_value' => $variable['min_value'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $template->refresh()->load('variables');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return [
            'guest_name' => 'علی رضایی',
            'hotel_name' => 'هتل پارسیان',
            'total_price' => 1000,
        ];
    }

    private function queuedDocument(Template $template): GeneratedDocument
    {
        Queue::fake();

        /** @var DocumentGenerationService $service */
        $service = $this->app->make(DocumentGenerationService::class);
        $document = $service->queue($template, $this->data());

        Queue::assertPushed(GenerateDocumentJob::class);

        return $document;
    }
}
