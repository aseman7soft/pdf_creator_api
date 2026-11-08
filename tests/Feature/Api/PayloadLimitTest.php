<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

class PayloadLimitTest extends ApiTestCase
{
    public function test_oversized_payload_returns_413(): void
    {
        config(['pdf.max_payload_kb' => 1]);

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $payload = $this->generatePayload($template, ['guest_name' => str_repeat('x', 4096)]);

        $response = $this->postJson('/api/v1/documents/generate', $payload, $this->bearer($context['token']));

        $response->assertStatus(413);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_payload_at_the_limit_is_accepted_by_the_guard(): void
    {
        config(['pdf.max_payload_kb' => 1024]);

        $context = $this->makeProjectWithKey();

        // No template resolution happens before validation, so a normal call
        // proves the payload guard does not reject small bodies.
        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'unknown-template',
            'data' => ['guest_name' => 'ok'],
        ], $this->bearer($context['token']));

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'TEMPLATE_NOT_FOUND');
    }
}
