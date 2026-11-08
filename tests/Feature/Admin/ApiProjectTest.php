<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_manage_projects(): void
    {
        $viewer = User::factory()->create();
        $project = ApiProject::factory()->create();

        $this->actingAs($viewer)->get('/admin/projects')->assertOk();
        $this->actingAs($viewer)->get('/admin/projects/create')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/projects', ['name' => 'x', 'code' => 'x-proj'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.projects.edit', $project))->assertForbidden();
    }

    public function test_manager_can_create_project_with_allowed_templates(): void
    {
        $manager = User::factory()->manager()->create();
        $templateA = Template::factory()->create();
        $templateB = Template::factory()->create();

        $this->actingAs($manager)->post('/admin/projects', [
            'name' => 'پروژه تست',
            'code' => 'test-project',
            'description' => 'توضیح پروژه',
            'is_active' => '1',
            'rate_limit_per_minute' => '30',
            'monthly_quota' => '1000',
            'templates' => [$templateA->id, $templateB->id],
        ])->assertRedirect();

        $project = ApiProject::query()->where('code', 'test-project')->first();

        $this->assertNotNull($project);
        $this->assertSame(30, $project->rate_limit_per_minute);
        $this->assertSame(1000, $project->monthly_quota);
        $this->assertTrue($project->is_active);
        $this->assertEqualsCanonicalizing(
            [$templateA->id, $templateB->id],
            $project->templates()->pluck('templates.id')->all(),
        );

        $this->actingAs($manager)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertSee('پروژه تست');
    }

    public function test_project_without_templates_has_no_restrictions(): void
    {
        $manager = User::factory()->manager()->create();
        Template::factory()->create();

        $this->actingAs($manager)->post('/admin/projects', [
            'name' => 'پروژه آزاد',
            'code' => 'free-project',
            'is_active' => '1',
        ])->assertRedirect();

        $project = ApiProject::query()->where('code', 'free-project')->firstOrFail();

        $this->assertCount(0, $project->templates);
        $this->assertFalse($project->hasTemplateRestrictions());
    }

    public function test_project_update_syncs_templates(): void
    {
        $manager = User::factory()->manager()->create();
        $project = ApiProject::factory()->create(['code' => 'sync-project']);
        $template = Template::factory()->create();

        $this->actingAs($manager)->put(route('admin.projects.update', $project), [
            'name' => 'نام جدید',
            'code' => 'sync-project',
            'is_active' => '1',
            'templates' => [$template->id],
        ])->assertRedirect(route('admin.projects.show', $project));

        $this->assertSame('نام جدید', $project->fresh()->name);
        $this->assertCount(1, $project->fresh()->templates);

        $this->actingAs($manager)->put(route('admin.projects.update', $project), [
            'name' => 'نام جدید',
            'code' => 'sync-project',
            'is_active' => '1',
            'templates' => [],
        ])->assertRedirect();

        $this->assertCount(0, $project->fresh()->templates);
    }

    public function test_api_key_is_shown_exactly_once(): void
    {
        $manager = User::factory()->manager()->create();
        $project = ApiProject::factory()->create();

        $response = $this->actingAs($manager)->post(route('admin.projects.keys.store', $project), [
            'name' => 'کلید سرور اصلی',
        ]);

        $response->assertRedirect(route('admin.projects.show', $project));

        $plain = session('plain_key');
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);

        $key = ApiKey::query()->where('api_project_id', $project->id)->firstOrFail();
        $this->assertSame('کلید سرور اصلی', $key->name);
        $this->assertSame(substr($plain, 0, 20), $key->key_prefix);
        $this->assertNotSame($plain, $key->key_hash);

        // First page load after the redirect displays the plain key.
        $this->actingAs($manager)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertSee($plain)
            ->assertSee('فقط همین یک بار', false);

        // Reloading the page must not expose the plain key again.
        $this->actingAs($manager)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertDontSee($plain);
    }

    public function test_api_key_can_be_rotated_and_revoked(): void
    {
        $manager = User::factory()->manager()->create();
        $project = ApiProject::factory()->create();
        $key = ApiKey::factory()->create(['api_project_id' => $project->id]);

        $this->actingAs($manager)
            ->post(route('admin.projects.keys.rotate', [$project, $key]))
            ->assertRedirect(route('admin.projects.show', $project));

        $this->assertNotNull($key->fresh()->revoked_at);
        $this->assertSame(2, $project->apiKeys()->count());
        $this->assertNotNull(session('plain_key'));

        $newKey = $project->apiKeys()->latest('id')->first();
        $this->assertNotNull($newKey);

        $this->actingAs($manager)
            ->post(route('admin.projects.keys.revoke', [$project, $newKey]))
            ->assertRedirect();

        $this->assertFalse($newKey->fresh()->isUsable());
    }

    public function test_api_key_can_be_deleted(): void
    {
        $manager = User::factory()->manager()->create();
        $project = ApiProject::factory()->create();
        $key = ApiKey::factory()->create(['api_project_id' => $project->id]);

        $this->actingAs($manager)
            ->delete(route('admin.projects.keys.destroy', [$project, $key]))
            ->assertRedirect();

        $this->assertSoftDeleted('api_keys', ['id' => $key->id]);
    }

    public function test_project_can_be_toggled_and_deleted(): void
    {
        $manager = User::factory()->manager()->create();
        $project = ApiProject::factory()->create();

        $this->actingAs($manager)->post(route('admin.projects.toggle', $project))->assertRedirect();
        $this->assertFalse($project->fresh()->is_active);

        $this->actingAs($manager)
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        $this->assertSoftDeleted('api_projects', ['id' => $project->id]);
    }
}
