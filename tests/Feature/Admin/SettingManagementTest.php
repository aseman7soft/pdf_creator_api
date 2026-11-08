<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['status' => 'up', 'version' => '8.0.0'], 200)]);
    }

    public function test_admin_can_view_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('ØªÙ†Ø¸ÛŒÙ…Ø§Øª')
            ->assertSee('Ø¹Ù…ÙˆÙ…ÛŒ')
            ->assertSee('Gotenberg');
    }

    public function test_manager_and_viewer_cannot_view_settings(): void
    {
        $manager = User::factory()->manager()->create();
        $viewer = User::factory()->create();

        $this->actingAs($manager)->get('/admin/settings')->assertForbidden();
        $this->actingAs($viewer)->get('/admin/settings')->assertForbidden();

        $this->actingAs($manager)->put('/admin/settings', [])->assertForbidden();
        $this->actingAs($viewer)->put('/admin/settings', [])->assertForbidden();
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'general' => [
                    'app_name' => 'Ø³Ø±ÙˆÛŒØ³ ØªØ³Øª',
                    'brand_color' => '#123456',
                    'support_email' => 'help@example.com',
                    'items_per_page' => '25',
                ],
                'documents' => [
                    'retention_days' => '7',
                    'default_format' => 'A5',
                    'max_payload_kb' => '2048',
                    'signed_url_ttl' => '15',
                ],
                'api' => [
                    'rate_limit_per_minute' => '120',
                    'idempotency_ttl_hours' => '12',
                ],
                'gotenberg' => [
                    'url' => 'http://127.0.0.1:3000',
                    'timeout' => '45',
                ],
                'security' => [
                    'allow_remote_images' => '1',
                    'max_upload_kb' => '1024',
                ],
                'queue' => [
                    'name' => 'documents',
                    'tries' => '5',
                    'timeout' => '240',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'general.app_name', 'value' => 'Ø³Ø±ÙˆÛŒØ³ ØªØ³Øª']);
        $this->assertDatabaseHas('settings', ['key' => 'documents.default_format', 'value' => 'A5']);
        $this->assertDatabaseHas('settings', ['key' => 'general.items_per_page', 'value' => '25']);

        $this->assertSame('Ø³Ø±ÙˆÛŒØ³ ØªØ³Øª', app(SettingsService::class)->get('general.app_name'));
        $this->assertTrue((bool) app(SettingsService::class)->get('security.allow_remote_images'));

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Ø³Ø±ÙˆÛŒØ³ ØªØ³Øª');
    }

    public function test_invalid_brand_color_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['general' => ['brand_color' => 'blue']])
            ->assertSessionHasErrors('general.brand_color');
    }

    public function test_gotenberg_connection_test_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.test-gotenberg'))
            ->assertRedirect();

        $this->assertTrue(session()->has('success') || session()->has('error'));

        $this->actingAs($admin)
            ->postJson(route('admin.settings.test-gotenberg'))
            ->assertOk()
            ->assertJsonStructure(['available', 'message']);
    }

    public function test_settings_are_not_secret_leaking(): void
    {
        Setting::query()->create([
            'group' => 'security',
            'key' => 'security.some_secret',
            'value' => 'super-secret-value',
            'type' => 'string',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('super-secret-value');
    }
}
