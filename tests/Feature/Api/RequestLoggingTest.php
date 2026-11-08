<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiRequestLog;

class RequestLoggingTest extends ApiTestCase
{
    public function test_successful_request_is_logged_with_request_id(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));
        $response->assertOk();

        $requestId = (string) $response->headers->get('X-Request-Id');
        $this->assertNotEmpty($requestId);
        $this->assertSame($requestId, $response->json('request_id'));

        $log = ApiRequestLog::query()->where('request_id', $requestId)->first();

        $this->assertNotNull($log);
        $this->assertSame('GET', $log->method);
        $this->assertSame('/api/v1/templates', $log->path);
        $this->assertSame('api.templates.index', $log->route_name);
        $this->assertSame(200, $log->status_code);
        $this->assertSame($context['project']->getKey(), $log->api_project_id);
        $this->assertSame($context['key']->getKey(), $log->api_key_id);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertGreaterThan(0, (int) $log->response_bytes);
        $this->assertNull($log->error_code);
    }

    public function test_unauthorized_request_is_logged(): void
    {
        $response = $this->getJson('/api/v1/templates');
        $response->assertUnauthorized();

        $requestId = (string) $response->headers->get('X-Request-Id');

        $log = ApiRequestLog::query()->where('request_id', $requestId)->first();

        $this->assertNotNull($log);
        $this->assertSame(401, $log->status_code);
        $this->assertSame('INVALID_API_KEY', $log->error_code);
        $this->assertNull($log->api_project_id);
        $this->assertNull($log->api_key_id);
    }

    public function test_request_log_never_contains_authorization_or_payload_secrets(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate();

        $secretReference = 'SUPER-SECRET-REFERENCE-123';
        $secretData = 'SUPER-SECRET-DATA-456';

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'reference' => $secretReference,
            'data' => ['guest_name' => $secretData],
        ], $this->bearer($context['token']));

        $requestId = (string) $response->headers->get('X-Request-Id');
        $log = ApiRequestLog::query()->where('request_id', $requestId)->first();

        $this->assertNotNull($log);

        $dumped = json_encode($log->getAttributes(), JSON_UNESCAPED_UNICODE);
        $this->assertIsString($dumped);

        $this->assertStringNotContainsString($secretReference, $dumped);
        $this->assertStringNotContainsString($secretData, $dumped);
        $this->assertStringNotContainsString($context['token'], $dumped);
        $this->assertStringNotContainsString('Authorization', $dumped);
    }

    public function test_validation_error_is_logged_without_payload_details(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => '',
            'data' => ['guest_name' => 'SECRET-VALUE'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);

        $log = ApiRequestLog::query()
            ->where('request_id', $response->headers->get('X-Request-Id'))
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(422, $log->status_code);
        $this->assertSame('VALIDATION_ERROR', $log->error_code);
        $this->assertStringNotContainsString('SECRET-VALUE', (string) $log->error_message);
    }
}
