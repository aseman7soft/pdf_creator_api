<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\GeneratedDocument;
use App\Models\IdempotencyKey;

class IdempotencyTest extends ApiTestCase
{
    public function test_sync_generate_replays_the_same_document_and_pdf(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());
        $payload = $this->generatePayload($template);

        $headers = $this->bearer($context['token']) + ['Idempotency-Key' => 'idem-sync-001'];

        $first = $this->postJson('/api/v1/documents/generate', $payload, $headers);
        $this->assertPdfResponse($first);

        $second = $this->postJson('/api/v1/documents/generate', $payload, $headers);
        $this->assertPdfResponse($second);

        $this->assertSame($first->headers->get('X-Document-Id'), $second->headers->get('X-Document-Id'));
        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
        $this->assertSame(1, GeneratedDocument::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->count());

        $record = IdempotencyKey::query()->firstOrFail();
        $this->assertSame(200, $record->response_status);
        $this->assertNotNull($record->generated_document_id);
        $this->assertTrue($record->expires_at->isFuture());
    }

    public function test_same_key_with_different_body_returns_422(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $headers = $this->bearer($context['token']) + ['Idempotency-Key' => 'idem-reuse-001'];

        $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'data' => ['guest_name' => 'Ali'],
        ], $headers)->assertOk();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'data' => ['guest_name' => 'Reza'],
        ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, GeneratedDocument::query()->count());
    }

    public function test_queue_replay_returns_the_same_document_id(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());
        $payload = $this->generatePayload($template);

        $headers = $this->bearer($context['token']) + ['Idempotency-Key' => 'idem-queue-001'];

        $first = $this->postJson('/api/v1/documents/queue', $payload, $headers)->assertStatus(202);
        $second = $this->postJson('/api/v1/documents/queue', $payload, $headers)->assertStatus(202);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
        $this->assertSame(1, GeneratedDocument::query()->count());

        $record = IdempotencyKey::query()->firstOrFail();
        $this->assertSame(202, $record->response_status);
    }

    public function test_expired_idempotency_record_allows_a_new_generation(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());
        $payload = $this->generatePayload($template);

        $headers = $this->bearer($context['token']) + ['Idempotency-Key' => 'idem-expired-001'];

        $this->postJson('/api/v1/documents/generate', $payload, $headers)->assertOk();

        IdempotencyKey::query()->update(['expires_at' => now()->subMinute()]);

        $second = $this->postJson('/api/v1/documents/generate', $payload, $headers);
        $this->assertPdfResponse($second);
        $this->assertSame(2, GeneratedDocument::query()->count());
    }

    public function test_invalid_idempotency_key_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'data' => ['guest_name' => 'Ali'],
        ], $this->bearer($context['token']) + ['Idempotency-Key' => 'invalid key with spaces']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_INVALID');
    }
}
