<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DefaultAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The credentials the project ships as its default admin login.
     */
    private const DEFAULT_EMAIL = 'admin@tarahsite.net';

    private const DEFAULT_PASSWORD = 'As123321_14';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.default_user.name' => 'مدیر سیستم',
            'admin.default_user.email' => self::DEFAULT_EMAIL,
            'admin.default_user.password' => self::DEFAULT_PASSWORD,
        ]);
    }

    public function test_config_ships_the_documented_default_credentials(): void
    {
        /** @var array{default_user: array{email: string, password: string}} $file */
        $file = require config_path('admin.php');

        $this->assertSame('admin@tarahsite.net', $file['default_user']['email']);
        $this->assertSame('As123321_14', $file['default_user']['password']);
    }

    public function test_seeder_creates_the_default_admin_account(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', self::DEFAULT_EMAIL)->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::DEFAULT_PASSWORD, (string) $user->getRawOriginal('password')));
    }

    public function test_default_admin_can_log_in_to_the_panel(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->post('/admin/login', [
            'email' => self::DEFAULT_EMAIL,
            'password' => self::DEFAULT_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->get('/admin')->assertOk();
    }

    public function test_reseeding_is_idempotent_and_keeps_a_changed_password(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());

        $user = User::query()->where('email', self::DEFAULT_EMAIL)->firstOrFail();
        $user->forceFill(['password' => 'Changed-Password-99'])->save();

        // An empty ADMIN_PASSWORD must not reset an existing admin password.
        config(['admin.default_user.password' => '']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('Changed-Password-99', (string) $user->fresh()->getRawOriginal('password')));
    }

    public function test_empty_password_generates_a_random_one_on_create(): void
    {
        config(['admin.default_user.password' => '']);

        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', self::DEFAULT_EMAIL)->firstOrFail();

        $this->assertFalse(Hash::check('', (string) $user->getRawOriginal('password')));
        $this->assertNotSame('', (string) $user->getRawOriginal('password'));
    }
}
