<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use App\Enums\JobStatus;
use App\Models\ApiProject;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The Gotenberg probe must never hit the network in tests.
        Http::fake(['*' => Http::response(['status' => 'up'], 200)]);
    }

    public function test_dashboard_renders_for_every_role(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->manager()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->get('/admin')
                ->assertOk()
                ->assertSee('داشبورد');
        }
    }

    public function test_dashboard_computes_statistics(): void
    {
        $template = Template::factory()->create();
        $project = ApiProject::factory()->create();

        GeneratedDocument::factory()->count(2)->create([
            'template_id' => $template->id,
            'api_project_id' => $project->id,
            'status' => DocumentStatus::Completed,
            'generation_mode' => GenerationMode::Sync,
        ]);
        GeneratedDocument::factory()->create([
            'template_id' => $template->id,
            'api_project_id' => $project->id,
            'status' => DocumentStatus::Failed,
        ]);

        DocumentJob::query()->create([
            'generated_document_id' => GeneratedDocument::query()->first()->id,
            'status' => JobStatus::Completed,
            'attempts' => 1,
            'max_attempts' => 3,
            'duration_ms' => 1500,
        ]);

        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin')->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(3, $stats['documents_total']);
        $this->assertSame(3, $stats['documents_today']);
        $this->assertSame(2, $stats['documents_completed']);
        $this->assertSame(1, $stats['documents_failed']);
        $this->assertSame(1, $stats['projects_active']);
        $this->assertSame(1, $stats['templates_active']);
        $this->assertEquals(1500.0, $stats['avg_generation_ms']);
    }

    public function test_dashboard_shows_recent_documents_and_queue_health(): void
    {
        $document = GeneratedDocument::factory()->completed()->create([
            'reference' => 'REF-DASHBOARD-1',
        ]);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('سلامت صف')
            ->assertSee('وضعیت Gotenberg')
            ->assertSee('آخرین اسناد')
            ->assertSee($document->template->name);
    }

    public function test_dashboard_chart_has_seven_points(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin')->assertOk();

        $this->assertCount(7, $response->viewData('chart'));
    }

    public function test_inactive_user_is_redirected_away_from_dashboard(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.login'));
    }
}
