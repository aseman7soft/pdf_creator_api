<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        foreach ($settings->defaults() as $key => $definition) {
            $settings->set(
                key: $key,
                value: $definition['value'],
                type: $definition['type'],
                group: $definition['group'],
                description: $definition['description'],
                isPublic: $definition['is_public'],
            );
        }
    }
}
