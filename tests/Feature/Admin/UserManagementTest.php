<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        User::factory()->create(['name' => 'کاربر عادی']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('کاربر عادی')
            ->assertSee('مدیریت کاربران');
    }

    public function test_manager_and_viewer_cannot_manage_users(): void
    {
        $manager = User::factory()->manager()->create();
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($viewer)->get('/admin/users')->assertForbidden();

        $this->actingAs($manager)->post('/admin/users', [
            'name' => 'نفوذی',
            'email' => 'intruder@example.com',
            'role' => 'admin',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertForbidden();

        $this->actingAs($manager)->delete(route('admin.users.destroy', $target))->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'کاربر جدید',
            'email' => 'new-user@example.com',
            'role' => 'manager',
            'is_active' => '1',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'new-user@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Manager, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password@123', $user->password));
    }

    public function test_admin_can_update_user_role_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'نام جدید',
            'email' => 'target@example.com',
            'role' => 'manager',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('نام جدید', $user->name);
        $this->assertSame(UserRole::Manager, $user->role);
    }

    public function test_admin_can_toggle_user_and_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle', $user))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.users.password', $user), [
            'password' => 'BrandNew@123',
            'password_confirmation' => 'BrandNew@123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('BrandNew@123', $user->fresh()->password));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'manager',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_cannot_deactivate_self_via_update(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_last_active_admin_is_protected_from_self_deactivation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle', $admin))
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            User::query()->where('role', 'admin')->where('is_active', true)->count(),
        );

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_form_pages_render_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users/create')->assertOk()->assertSee('ایجاد کاربر جدید');
        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('بازنشانی رمز عبور');
    }
}
