<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Api\ApiKeyService;

class AuthenticationTest extends ApiTestCase
{
    public function test_missing_authorization_header_returns_401_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/templates');

        $response->assertUnauthorized();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
        $response->assertJsonStructure(['success', 'error' => ['code', 'message', 'details'], 'request_id']);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_unknown_api_key_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/templates', $this->bearer('pdfsvc_this-key-does-not-exist'));

        $response->assertUnauthorized();
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_expired_api_key_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();

        $context['key']->forceFill(['expires_at' => now()->subDay()])->save();

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertUnauthorized();
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_revoked_api_key_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();

        app(ApiKeyService::class)->revoke($context['key']);

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertUnauthorized();
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_inactive_project_is_rejected(): void
    {
        $context = $this->makeProjectWithKey(['is_active' => false]);

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertUnauthorized();
        $response->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_valid_key_can_access_protected_endpoint(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        $this->assertDatabaseHas('api_keys', [
            'id' => $context['key']->getKey(),
        ]);
        $this->assertNotNull($context['key']->refresh()->last_used_at);
    }
}
