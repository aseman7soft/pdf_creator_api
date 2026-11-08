<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Models\ApiProject;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueuePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    public function test_queue_page_renders_for_viewer(): void
    {
        $this->createFailedJob();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/admin/queue')
            ->assertOk()
            ->assertSee('صف پردازش')
            ->assertSee('Jobهای در صف')
            ->assertSee('Jobهای ناموفق');
    }

    public function test_queue_page_supports_status_filter(): void
    {
        $this->createFailedJob();
        $this->createJob(JobStatus::Completed);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/admin/queue?status=failed')
            ->assertOk()
            ->assertSee('GOTENBERG_UNAVAILABLE');

        $this->actingAs($viewer)
            ->get('/admin/queue?status=completed')
            ->assertOk();
    }

    public function test_manager_can_retry_a_document_job(): void
    {
        $job = $this->createFailedJob();
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)->post(route('admin.queue.retry', $job));

        $response->assertRedirect();
        $this->assertTrue(session()->has('success') || session()->has('error'));
    }

    public function test_viewer_cannot_retry_a_document_job(): void
    {
        $job = $this->createFailedJob();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->post(route('admin.queue.retry', $job))->assertForbidden();
    }

    public function test_manager_can_delete_a_document_job(): void
    {
        $job = $this->createFailedJob();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->delete(route('admin.queue.destroy', $job))
            ->assertRedirect();

        $this->assertDatabaseMissing('document_jobs', ['id' => $job->id]);
    }

    public function test_failed_jobs_actions_are_admin_only(): void
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'documents',
            'payload' => '{}',
            'exception' => 'TestException: something failed',
            'failed_at' => now(),
        ]);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)
            ->post(route('admin.queue.failed.retry', $uuid))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.queue.failed.retry', $uuid))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('admin.queue.failed.forget', $uuid))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    private function createJob(JobStatus $status): DocumentJob
    {
        $document = GeneratedDocument::factory()->create([
            'template_id' => Template::factory(),
            'api_project_id' => ApiProject::factory(),
            'status' => $status === JobStatus::Completed ? DocumentStatus::Completed : DocumentStatus::Failed,
        ]);

        return DocumentJob::query()->create([
            'generated_document_id' => $document->id,
            'queue' => 'documents',
            'status' => $status,
            'attempts' => 1,
            'max_attempts' => 3,
            'duration_ms' => 1200,
        ]);
    }

    private function createFailedJob(): DocumentJob
    {
        $job = $this->createJob(JobStatus::Failed);
        $job->forceFill([
            'error_code' => 'GOTENBERG_UNAVAILABLE',
            'error_message' => 'سرویس در دسترس نیست',
        ])->save();

        return $job->refresh();
    }
}
