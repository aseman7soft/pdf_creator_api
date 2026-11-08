<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\GeneratedDocument;
use Illuminate\Support\Facades\Storage;

class QueueDocumentTest extends ApiTestCase
{
    public function test_queue_returns_202_with_links(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $response = $this->postJson(
            '/api/v1/documents/queue',
            $this->generatePayload($template),
            $this->bearer($context['token']),
        );

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => ['id', 'status', 'status_url', 'download_url', 'created_at'],
            'request_id',
        ]);

        $this->assertNotEmpty($response->json('data.id'));
        $this->assertStringContainsString('/documents/'.$response->json('data.id').'/status', (string) $response->json('data.status_url'));
        $this->assertStringContainsString('signature=', (string) $response->json('data.download_url'));

        $document = GeneratedDocument::query()->firstOrFail();
        $this->assertSame('async', $document->generation_mode->value);
    }

    public function test_status_polling_and_both_download_paths_work(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $queueResponse = $this->postJson(
            '/api/v1/documents/queue',
            $this->generatePayload($template),
            $this->bearer($context['token']),
        )->assertStatus(202);

        $statusUrl = (string) $queueResponse->json('data.status_url');

        $statusResponse = $this->getJson($statusUrl, $this->bearer($context['token']));
        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('success', true);
        $statusResponse->assertJsonPath('data.status', 'completed');
        $statusResponse->assertJsonPath('data.template.slug', $template->slug);
        $statusResponse->assertJsonStructure(['data' => ['id', 'reference', 'status', 'template' => ['slug', 'name', 'version'], 'created_at', 'completed_at', 'file_size', 'download_url', 'error']]);

        $ulid = (string) $statusResponse->json('data.id');

        // Authenticated download.
        $download = $this->get('/api/v1/documents/'.$ulid.'/download', $this->bearer($context['token']));
        $this->assertPdfResponse($download);
        $this->assertStringStartsWith('%PDF', $download->streamedContent());

        // Temporary signed download (no bearer required).
        $signedUrl = (string) $statusResponse->json('data.download_url');
        $this->assertNotEmpty($signedUrl);

        $signed = $this->get($signedUrl);
        $this->assertPdfResponse($signed);
        $this->assertStringStartsWith('%PDF', $signed->streamedContent());
    }

    public function test_tampered_signed_download_url_is_rejected(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $queueResponse = $this->postJson(
            '/api/v1/documents/queue',
            $this->generatePayload($template),
            $this->bearer($context['token']),
        )->assertStatus(202);

        $signedUrl = (string) $queueResponse->json('data.download_url');
        $tampered = (string) preg_replace('/(signature=)[^&]+/', '${1}tampered', $signedUrl);

        $response = $this->get($tampered);

        $response->assertForbidden();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'SIGNATURE_INVALID');
    }

    public function test_status_of_unknown_document_returns_404(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->getJson(
            '/api/v1/documents/01JABCDEFGHJKMNPQRSTVWXYZ0/status',
            $this->bearer($context['token']),
        );

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'DOCUMENT_NOT_FOUND');
    }

    public function test_download_of_pending_document_returns_409(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $document = GeneratedDocument::factory()->create([
            'api_project_id' => $context['project']->getKey(),
            'template_id' => $template->getKey(),
            'status' => 'pending',
            'file_path' => null,
        ]);

        $response = $this->getJson(
            '/api/v1/documents/'.$document->ulid.'/download',
            $this->bearer($context['token']),
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'DOCUMENT_NOT_READY');
    }

    public function test_download_of_expired_document_returns_410(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $document = GeneratedDocument::factory()->completed()->create([
            'api_project_id' => $context['project']->getKey(),
            'template_id' => $template->getKey(),
            'expires_at' => now()->subHour(),
        ]);

        Storage::disk((string) config('pdf.disk', 'local'))->put($document->file_path, self::PDF_BYTES);

        $response = $this->getJson(
            '/api/v1/documents/'.$document->ulid.'/download',
            $this->bearer($context['token']),
        );

        $response->assertStatus(410);
        $response->assertJsonPath('error.code', 'DOCUMENT_EXPIRED');
    }
}
