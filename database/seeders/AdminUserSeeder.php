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
        $email = (string) (env('ADMIN_EMAIL') ?: 'admin@as7th.com');
        $password = (string) env('ADMIN_PASSWORD');
        $generated = false;

        if ($password === '') {
            $password = Str::password(16);
            $generated = true;
        }

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'مدیر سیستم',
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Admin user: {$user->email}");

        if ($generated) {
            $this->command?->warn("Generated admin password (shown once): {$password}");
        }
    }
}
