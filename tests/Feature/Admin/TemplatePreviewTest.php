<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Gotenberg is never available in tests: any engine HTTP call is faked.
        Http::fake([
            '*' => Http::response('%PDF-1.4 fake pdf body', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    public function test_viewer_can_open_preview_page(): void
    {
        $template = Template::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('admin.templates.preview', $template))
            ->assertOk()
            ->assertSee('داده نمونه')
            ->assertSee('پیش‌نمایش HTML')
            ->assertSee('پیش‌نمایش PDF');
    }

    public function test_html_preview_returns_a_page(): void
    {
        $template = Template::factory()->create([
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><body><h1>{{ guest_name }}</h1></body></html>',
        ]);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->post(route('admin.templates.preview.html', $template), [
            'data' => json_encode(['guest_name' => 'علی رضایی']),
        ]);

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_html_preview_with_invalid_json_does_not_fail(): void
    {
        $template = Template::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->post(route('admin.templates.preview.html', $template), ['data' => '{invalid json'])
            ->assertOk();

        $this->assertDatabaseCount('generated_documents', 0);
    }

    public function test_pdf_preview_never_returns_a_server_error(): void
    {
        $template = Template::factory()->create();
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->post(route('admin.templates.preview.pdf', $template), [
            'data' => json_encode(['guest_name' => 'علی رضایی']),
        ]);

        $this->assertContains($response->getStatusCode(), [200, 302]);
        $this->assertNotSame(500, $response->getStatusCode());

        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        }

        // Preview must never persist a generated document.
        $this->assertDatabaseCount('generated_documents', 0);
    }

    public function test_guest_cannot_preview_templates(): void
    {
        $template = Template::factory()->create();

        $this->get(route('admin.templates.preview', $template))->assertRedirect('/admin/login');
        $this->post(route('admin.templates.preview.pdf', $template), ['data' => '{}'])
            ->assertRedirect('/admin/login');
    }
}
