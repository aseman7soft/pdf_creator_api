<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Deactivated users cannot pass any authorisation check.
        Gate::before(function (User $user): ?bool {
            return $user->is_active === true ? null : false;
        });
    }
}
