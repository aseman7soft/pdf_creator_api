<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApiProject;
use App\Models\Template;
use App\Services\Api\ApiKeyService;
use Illuminate\Database\Seeder;

class DemoApiProjectSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn('Demo API project is only seeded in local/testing environments.');

            return;
        }

        $project = ApiProject::query()->firstOrCreate(
            ['code' => 'demo-project'],
            [
                'name' => 'پروژه نمایشی',
                'description' => 'پروژه نمونه برای تست API در محیط توسعه',
                'is_active' => true,
                'rate_limit_per_minute' => null,
            ],
        );

        $templateIds = Template::query()->whereIn('slug', [
            'hotel-voucher',
            'invoice-standard',
            'tour-itinerary',
        ])->pluck('id')->all();

        $project->templates()->syncWithoutDetaching($templateIds);

        if ($project->apiKeys()->exists()) {
            $this->command?->info('Demo project already has API keys; no new key generated.');

            return;
        }

        /** @var ApiKeyService $service */
        $service = app(ApiKeyService::class);
        $result = $service->create($project, 'کلید نمایشی');

        $this->command?->info('Demo API key (shown once): '.$result['plain']);
        $this->command?->info('Project code: '.$project->code);
    }
}
