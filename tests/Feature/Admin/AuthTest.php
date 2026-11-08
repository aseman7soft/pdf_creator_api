<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/templates')->assertRedirect('/admin/login');
        $this->get('/admin/documents')->assertRedirect('/admin/login');
    }

    public function test_login_page_renders_in_persian(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('ورود به پنل مدیریت')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_root_redirects_to_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->get('/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'Test-Password!23',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Test-Password!23',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_remember_me_sets_remember_token(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'remember@example.com',
            'password' => 'Test-Password!23',
        ]);

        $this->post('/admin/login', [
            'email' => 'remember@example.com',
            'password' => 'Test-Password!23',
            'remember' => '1',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'Test-Password!23',
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'Test-Password!23',
        ]);

        $this->post('/admin/login', [
            'email' => 'inactive@example.com',
            'password' => 'Test-Password!23',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/admin/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_deactivated_session_is_kicked_out(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user->fresh())
            ->get('/admin')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}
