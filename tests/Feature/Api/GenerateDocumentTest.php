<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\GeneratedDocument;
use App\Services\Api\ApiKeyService;
use Illuminate\Support\Facades\Storage;

class GenerateDocumentTest extends ApiTestCase
{
    public function test_sync_generate_streams_pdf_and_persists_document(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $response = $this->postJson(
            '/api/v1/documents/generate',
            $this->generatePayload($template, ['guest_name' => 'علی رضایی']),
            $this->bearer($context['token']),
        );

        $this->assertPdfResponse($response);

        $documentId = $response->headers->get('X-Document-Id');
        $this->assertNotEmpty($documentId);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $documentId);
        $this->assertIsNumeric($response->headers->get('X-Generation-Time-Ms'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        $this->assertStringStartsWith('%PDF', $response->streamedContent());

        $document = GeneratedDocument::query()->firstOrFail();

        $this->assertSame($document->ulid, $documentId);
        $this->assertSame('completed', $document->status->value);
        $this->assertSame('sync', $document->generation_mode->value);
        $this->assertSame($context['project']->getKey(), $document->api_project_id);
        $this->assertSame($template->getKey(), $document->template_id);
        $this->assertNotNull($document->file_path);
        $this->assertGreaterThan(0, (int) $document->file_size);
        Storage::disk((string) config('pdf.disk', 'local'))->assertExists($document->file_path);

        $this->assertDatabaseHas('api_request_logs', [
            'request_id' => $response->headers->get('X-Request-Id'),
            'method' => 'POST',
            'status_code' => 200,
            'api_project_id' => $context['project']->getKey(),
            'api_key_id' => $context['key']->getKey(),
            'template_id' => $template->getKey(),
            'generated_document_id' => $document->getKey(),
        ]);
    }

    public function test_generation_failure_marks_document_failed_and_returns_503(): void
    {
        $this->fakeGotenbergFailure();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $response = $this->postJson(
            '/api/v1/documents/generate',
            $this->generatePayload($template),
            $this->bearer($context['token']),
        );

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'GOTENBERG_UNAVAILABLE');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        $document = GeneratedDocument::query()->firstOrFail();
        $this->assertSame('failed', $document->status->value);
        $this->assertSame('GOTENBERG_UNAVAILABLE', $document->error_code);

        $this->assertDatabaseHas('api_request_logs', [
            'request_id' => $response->headers->get('X-Request-Id'),
            'status_code' => 503,
            'error_code' => 'GOTENBERG_UNAVAILABLE',
        ]);
    }

    public function test_generate_requires_authentication(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $response = $this->postJson('/api/v1/documents/generate', $this->generatePayload($template));

        $response->assertUnauthorized();
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_generate_rejects_unknown_template(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'missing-template',
            'data' => ['guest_name' => 'Ali'],
        ], $this->bearer($context['token']));

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'TEMPLATE_NOT_FOUND');
    }

    public function test_generate_rejects_inactive_template(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate(['is_active' => false]);

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'data' => ['guest_name' => 'Ali'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TEMPLATE_INACTIVE');
    }

    public function test_generate_rejects_template_outside_project_scope(): void
    {
        $context = $this->makeProjectWithKey();
        $allowed = $this->makeTemplate();
        $forbidden = $this->makeTemplate();
        $context['project']->templates()->attach($allowed->getKey());

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $forbidden->slug,
            'data' => ['guest_name' => 'Ali'],
        ], $this->bearer($context['token']));

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'TEMPLATE_NOT_ALLOWED');
    }

    public function test_generate_does_not_leak_other_projects_documents(): void
    {
        $this->fakeGotenbergPdf();

        $owner = $this->makeProjectWithKey();
        $other = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $this->postJson(
            '/api/v1/documents/generate',
            $this->generatePayload($template),
            $this->bearer($owner['token']),
        )->assertOk();

        $document = GeneratedDocument::query()->firstOrFail();

        $response = $this->getJson(
            '/api/v1/documents/'.$document->ulid.'/status',
            $this->bearer($other['token']),
        );

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'DOCUMENT_NOT_FOUND');
    }

    public function test_revoked_key_cannot_generate(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        app(ApiKeyService::class)->revoke($context['key']);

        $this->postJson(
            '/api/v1/documents/generate',
            $this->generatePayload($template),
            $this->bearer($context['token']),
        )->assertUnauthorized();

        $this->assertSame(0, GeneratedDocument::query()->count());
    }
}
