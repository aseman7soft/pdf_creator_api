<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ApiProject;
use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_log_index_renders_with_filters(): void
    {
        $project = ApiProject::factory()->create(['name' => 'پروژه لاگ']);
        $this->createLog(['status_code' => 200, 'request_id' => 'REQ-OK-1', 'api_project_id' => $project->id]);
        $this->createLog(['status_code' => 500, 'request_id' => 'REQ-FAIL-1', 'api_project_id' => $project->id]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/admin/logs')
            ->assertOk()
            ->assertSee('REQ-OK-1')
            ->assertSee('REQ-FAIL-1');

        $this->actingAs($viewer)
            ->get('/admin/logs?status_class=5xx')
            ->assertOk()
            ->assertSee('REQ-FAIL-1')
            ->assertDontSee('REQ-OK-1');

        $this->actingAs($viewer)
            ->get('/admin/logs?search=REQ-OK-1')
            ->assertOk()
            ->assertSee('REQ-OK-1')
            ->assertDontSee('REQ-FAIL-1');

        $this->actingAs($viewer)
            ->get('/admin/logs?project_id='.$project->id)
            ->assertOk()
            ->assertSee('REQ-OK-1');
    }

    public function test_log_detail_page_renders(): void
    {
        $log = $this->createLog(['request_id' => 'REQ-DETAIL-1', 'error_code' => 'GOTENBERG_TIMEOUT']);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('admin.logs.show', $log))
            ->assertOk()
            ->assertSee('REQ-DETAIL-1')
            ->assertSee('GOTENBERG_TIMEOUT');
    }

    public function test_only_admin_can_purge_logs(): void
    {
        $old = $this->createLog(['request_id' => 'REQ-OLD-1']);
        $old->forceFill(['created_at' => now()->subDays(90)])->save();
        $fresh = $this->createLog(['request_id' => 'REQ-NEW-1']);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)
            ->delete(route('admin.logs.purge'), ['days' => 30])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->delete(route('admin.logs.purge'), ['days' => 30])
            ->assertRedirect();

        $this->assertDatabaseMissing('api_request_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('api_request_logs', ['id' => $fresh->id]);
    }

    public function test_app_log_viewer_is_admin_only(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('admin.logs.app'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.logs.app'))
            ->assertOk()
            ->assertSee('لاگ برنامه');
    }

    public function test_app_log_viewer_supports_level_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.logs.app').'?level=ERROR')
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.logs.app').'?search=Gotenberg')
            ->assertOk();
    }

    public function test_guest_cannot_view_logs(): void
    {
        $this->get('/admin/logs')->assertRedirect('/admin/login');
        $this->get('/admin/logs/app')->assertRedirect('/admin/login');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLog(array $overrides = []): ApiRequestLog
    {
        return ApiRequestLog::query()->create(array_merge([
            'request_id' => (string) Str::uuid(),
            'method' => 'POST',
            'path' => '/api/v1/documents/generate',
            'route_name' => 'api.documents.generate',
            'status_code' => 200,
            'duration_ms' => 850,
            'request_bytes' => 1200,
            'response_bytes' => 2048,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ], $overrides));
    }
}
