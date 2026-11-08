<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use App\Models\ApiProject;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    public function test_index_supports_search_and_filters(): void
    {
        $project = ApiProject::factory()->create();
        $template = Template::factory()->create(['name' => 'قالب خاص']);
        $viewer = User::factory()->create();

        $match = GeneratedDocument::factory()->completed()->create([
            'reference' => 'REF-MATCH-1',
            'api_project_id' => $project->id,
            'template_id' => $template->id,
            'template_name' => 'قالب خاص',
        ]);
        $other = GeneratedDocument::factory()->failed()->create([
            'reference' => 'REF-OTHER-2',
            'api_project_id' => $project->id,
            'template_id' => $template->id,
            'template_name' => 'قالب دیگر',
        ]);

        $this->actingAs($viewer)
            ->get('/admin/documents?search=REF-MATCH-1')
            ->assertOk()
            ->assertSee('REF-MATCH-1')
            ->assertDontSee('REF-OTHER-2');

        $this->actingAs($viewer)
            ->get('/admin/documents?status=failed')
            ->assertOk()
            ->assertSee('REF-OTHER-2')
            ->assertDontSee('REF-MATCH-1');

        $this->actingAs($viewer)
            ->get('/admin/documents?project_id='.$project->id)
            ->assertOk()
            ->assertSee('REF-MATCH-1');

        $this->actingAs($viewer)
            ->get('/admin/documents?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertSee('REF-MATCH-1');
    }

    public function test_document_detail_page_renders_metadata(): void
    {
        $document = GeneratedDocument::factory()->completed()->create([
            'reference' => 'REF-DETAIL-1',
            'pdf_meta' => ['duration_ms' => 900, 'pages' => 2],
        ]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('admin.documents.show', $document))
            ->assertOk()
            ->assertSee($document->ulid)
            ->assertSee('REF-DETAIL-1')
            ->assertSee('فراداده PDF');
    }

    public function test_completed_document_can_be_downloaded(): void
    {
        $path = 'documents/2026/01/test.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 downloaded');

        $document = GeneratedDocument::factory()->completed()->create([
            'file_path' => $path,
            'file_size' => 20,
            'status' => DocumentStatus::Completed,
            'generation_mode' => GenerationMode::Sync,
        ]);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('admin.documents.download', $document));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF-1.4 downloaded', (string) $response->getContent());
    }

    public function test_pending_document_cannot_be_downloaded(): void
    {
        $document = GeneratedDocument::factory()->create(['status' => DocumentStatus::Pending]);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('admin.documents.download', $document))
            ->assertForbidden();
    }

    public function test_signed_download_url_is_available(): void
    {
        $path = 'documents/2026/01/signed.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 signed');

        $document = GeneratedDocument::factory()->completed()->create(['file_path' => $path]);
        $viewer = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'admin.documents.download.signed',
            now()->addMinutes(10),
            ['document' => $document->id],
        );

        $this->actingAs($viewer)->get($url)->assertOk();

        $tampered = $url.'&tampered=1';
        $this->actingAs($viewer)->get($tampered)->assertForbidden();
    }

    public function test_viewer_cannot_retry_or_delete_documents(): void
    {
        $document = GeneratedDocument::factory()->failed()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->post(route('admin.documents.retry', $document))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.documents.destroy', $document))->assertForbidden();
    }

    public function test_manager_can_delete_document_and_file(): void
    {
        $path = 'documents/2026/01/delete-me.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 delete');

        $document = GeneratedDocument::factory()->completed()->create(['file_path' => $path]);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->delete(route('admin.documents.destroy', $document))
            ->assertRedirect(route('admin.documents.index'));

        $this->assertSoftDeleted('generated_documents', ['id' => $document->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_manager_retry_on_failed_document_redirects_with_message(): void
    {
        $document = GeneratedDocument::factory()->failed()->create();
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)->post(route('admin.documents.retry', $document));

        $response->assertRedirect();
        $this->assertTrue(session()->has('success') || session()->has('error'));
    }

    public function test_guest_cannot_access_documents(): void
    {
        $this->get('/admin/documents')->assertRedirect('/admin/login');
    }
}
