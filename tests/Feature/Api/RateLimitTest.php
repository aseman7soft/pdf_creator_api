<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

class RateLimitTest extends ApiTestCase
{
    public function test_per_key_rate_limit_returns_429_with_headers(): void
    {
        $context = $this->makeProjectWithKey(['rate_limit_per_minute' => 2]);

        $this->getJson('/api/v1/templates', $this->bearer($context['token']))
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '2');

        $this->getJson('/api/v1/templates', $this->bearer($context['token']))->assertOk();

        $blocked = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $blocked->assertStatus(429);
        $blocked->assertJsonPath('success', false);
        $blocked->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
        $blocked->assertHeader('X-RateLimit-Limit', '2');
        $blocked->assertHeader('X-RateLimit-Remaining', '0');
        $this->assertNotEmpty($blocked->headers->get('Retry-After'));
        $this->assertNotEmpty($blocked->headers->get('X-RateLimit-Reset'));

        $this->assertDatabaseHas('api_request_logs', [
            'request_id' => $blocked->headers->get('X-Request-Id'),
            'status_code' => 429,
            'error_code' => 'RATE_LIMIT_EXCEEDED',
        ]);
    }

    public function test_monthly_quota_exhaustion_returns_429(): void
    {
        $context = $this->makeProjectWithKey(['monthly_quota' => 1]);

        $first = $this->getJson('/api/v1/templates', $this->bearer($context['token']));
        $first->assertOk();

        $blocked = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $blocked->assertStatus(429);
        $blocked->assertJsonPath('error.code', 'MONTHLY_QUOTA_EXCEEDED');
        $this->assertNotEmpty($blocked->headers->get('Retry-After'));
        $this->assertSame('0', $blocked->headers->get('X-RateLimit-Remaining'));
    }

    public function test_rate_limit_uses_project_default_when_key_has_no_override(): void
    {
        $context = $this->makeProjectWithKey(['rate_limit_per_minute' => 1]);

        $this->getJson('/api/v1/templates', $this->bearer($context['token']))->assertOk();
        $this->getJson('/api/v1/templates', $this->bearer($context['token']))->assertStatus(429);
    }
}
