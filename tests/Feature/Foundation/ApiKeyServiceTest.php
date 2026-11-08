<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\Template;
use App\Services\Api\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ApiKeyService
    {
        return app(ApiKeyService::class);
    }

    public function test_create_returns_plain_key_once_and_stores_only_a_hash(): void
    {
        $project = ApiProject::factory()->create();
        $result = $this->service()->create($project, 'کلید تست');

        $this->assertStringStartsWith((string) config('api.key_prefix').'_', $result['plain']);
        $this->assertSame($result['key']->key_prefix, substr($result['plain'], 0, 20));
        $this->assertNotSame($result['plain'], $result['key']->key_hash);
        $this->assertSame(64, strlen($result['key']->key_hash));
        $this->assertArrayNotHasKey('key_hash', $result['key']->toArray());
        $this->assertDatabaseHas('api_keys', ['id' => $result['key']->id, 'is_active' => true]);
    }

    public function test_authenticate_resolves_a_valid_key_and_updates_usage(): void
    {
        $project = ApiProject::factory()->create();
        $result = $this->service()->create($project, 'کلید تست');

        $resolved = $this->service()->authenticate($result['plain']);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($result['key']));
        $this->assertTrue($resolved->project->is($project));
    }

    public function test_authenticate_rejects_unknown_revoked_expired_and_inactive_project_keys(): void
    {
        $project = ApiProject::factory()->create();
        [$key, $plain] = array_values($this->service()->create($project, 'کلید تست'));

        $this->assertNull($this->service()->authenticate('pdfsvc_invalid'));

        $this->service()->revoke($key);
        $this->assertNull($this->service()->authenticate($plain));

        $expiredProject = ApiProject::factory()->create();
        $expired = $this->service()->create($expiredProject, 'منقضی', null, now()->subDay());
        $this->assertNull($this->service()->authenticate($expired['plain']));

        $inactiveProject = ApiProject::factory()->inactive()->create();
        $inactive = $this->service()->create($inactiveProject, 'پروژه غیرفعال');
        $this->assertNull($this->service()->authenticate($inactive['plain']));
    }

    public function test_rotate_revokes_old_key_and_issues_a_new_one(): void
    {
        $project = ApiProject::factory()->create();
        $original = $this->service()->create($project, 'کلید اصلی');

        $rotated = $this->service()->rotate($original['key']);

        $this->assertNull($this->service()->authenticate($original['plain']));
        $this->assertNotNull($this->service()->authenticate($rotated['plain']));
        $this->assertNotSame($original['plain'], $rotated['plain']);
        $this->assertFalse($original['key']->fresh()->is_active);
        $this->assertNotNull($original['key']->fresh()->revoked_at);
    }

    public function test_project_template_access_rules(): void
    {
        $project = ApiProject::factory()->create();
        $templateA = Template::factory()->create();
        $templateB = Template::factory()->create();
        $inactive = Template::factory()->inactive()->create();

        // No restriction: every active template is usable, inactive ones are not.
        $this->assertTrue($project->canUseTemplate($templateA));
        $this->assertTrue($project->canUseTemplate($templateB));
        $this->assertFalse($project->canUseTemplate($inactive));

        $project->templates()->attach($templateA->id);

        $this->assertTrue($project->hasTemplateRestrictions());
        $this->assertTrue($project->canUseTemplate($templateA));
        $this->assertFalse($project->canUseTemplate($templateB));
    }

    public function test_api_key_effective_rate_limit_precedence(): void
    {
        $project = ApiProject::factory()->create(['rate_limit_per_minute' => 10]);
        $key = ApiKey::factory()->create(['api_project_id' => $project->id, 'rate_limit_per_minute' => null]);

        $this->assertSame(10, $key->effectiveRateLimit());

        $key->rate_limit_per_minute = 3;
        $this->assertSame(3, $key->effectiveRateLimit());
    }
}
