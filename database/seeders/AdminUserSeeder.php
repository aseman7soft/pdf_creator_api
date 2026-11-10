<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $config = (array) config('admin.default_user', []);

        $name = (string) ($config['name'] ?? 'مدیر سیستم');
        $email = (string) ($config['email'] ?? 'admin@tarahsite.net');
        $password = (string) ($config['password'] ?? '');
        $generated = false;

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            $user->forceFill([
                'name' => $name,
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if ($password !== '' && ! Hash::check($password, (string) $user->getRawOriginal('password'))) {
                $user->password = $password;
            }

            $user->save();
        } else {
            if ($password === '') {
                $password = Str::password(16);
                $generated = true;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $this->command?->info("Admin user: {$user->email}");

        if ($generated) {
            $this->command?->warn("Generated admin password (shown once): {$password}");
        }
    }
}
