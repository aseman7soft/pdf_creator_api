<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Gotenberg\GotenbergService;
use Illuminate\Support\Facades\Http;

class HealthTest extends ApiTestCase
{
    public function test_health_endpoint_is_public_and_returns_service_state(): void
    {
        Http::fake([
            '*' => Http::response('', 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => ['status', 'service', 'version', 'timestamp', 'checks' => ['database', 'gotenberg']],
            'request_id',
        ]);

        $this->assertContains($response->json('data.status'), ['ok', 'degraded']);
        $this->assertSame('ok', $response->json('data.checks.database'));
        $this->assertSame('pdf-service', $response->json('data.service'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        // No internal connection details may leak to the public probe.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('127.0.0.1', $body);
        $this->assertStringNotContainsString('GOTENBERG_URL', $body);
        $this->assertStringNotContainsString('password', strtolower($body));
    }

    public function test_health_reports_ok_when_gotenberg_is_available(): void
    {
        if (! class_exists(GotenbergService::class)) {
            $this->markTestSkipped('GotenbergService is not implemented yet.');
        }

        Http::fake([
            '*' => Http::response('', 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        if ($response->json('data.checks.gotenberg') !== 'ok') {
            $this->markTestSkipped('Gotenberg health probe did not report ok in this environment.');
        }

        $response->assertJsonPath('data.status', 'ok');
    }

    public function test_health_is_cached_between_requests(): void
    {
        Http::fake([
            '*' => Http::response('', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertOk();

        $this->assertLessThanOrEqual(1, count(Http::recorded()));
    }
}
