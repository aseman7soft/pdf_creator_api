<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

class DocsTest extends ApiTestCase
{
    public function test_swagger_ui_page_is_public_and_points_to_the_spec(): void
    {
        $response = $this->get('/api/v1/docs');

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $this->assertStringContainsString('swagger-ui', $body);
        $this->assertStringContainsString('/api/v1/openapi.yaml', $body);
    }

    public function test_openapi_yaml_is_served_without_authentication(): void
    {
        $response = $this->get('/api/v1/openapi.yaml');

        $response->assertOk();
        $this->assertStringContainsString('yaml', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $this->assertStringContainsString('openapi: 3.0.3', $body);
        $this->assertStringContainsString('/documents/generate', $body);
        $this->assertStringContainsString('/documents/queue', $body);
        $this->assertStringContainsString('/documents/{ulid}/status', $body);
        $this->assertStringContainsString('/documents/{ulid}/download/signed', $body);
        $this->assertStringContainsString('bearerAuth', $body);
    }

    public function test_unknown_api_route_returns_json_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/definitely-not-a-route');

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
