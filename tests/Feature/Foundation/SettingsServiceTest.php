<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_available_without_database_rows(): void
    {
        $service = app(SettingsService::class);

        $this->assertSame('سرویس مرکزی تولید اسناد', $service->get('general.app_name'));
        $this->assertSame(30, $service->get('documents.retention_days'));
        $this->assertSame(60, $service->get('api.rate_limit_per_minute'));
    }

    public function test_set_persists_typed_values_and_flushes_cache(): void
    {
        $service = app(SettingsService::class);
        $service->set('documents.retention_days', 7);
        $service->set('security.allow_remote_images', false);
        $service->set('general.app_name', 'سرویس جدید');

        $this->assertSame(7, $service->get('documents.retention_days'));
        $this->assertFalse($service->get('security.allow_remote_images'));
        $this->assertSame('سرویس جدید', $service->get('general.app_name'));

        $this->assertDatabaseHas('settings', [
            'key' => 'documents.retention_days',
            'value' => '7',
            'type' => 'integer',
        ]);

        $this->assertSame('integer', Setting::query()->where('key', 'documents.retention_days')->value('type'));
    }

    public function test_settings_are_cached_until_flushed(): void
    {
        $service = app(SettingsService::class);
        $service->set('general.app_name', 'مقدار اول');
        $this->assertSame('مقدار اول', $service->get('general.app_name'));

        Setting::query()->where('key', 'general.app_name')->update(['value' => 'تغییر مستقیم']);

        // Still the cached value.
        $this->assertSame('مقدار اول', $service->get('general.app_name'));

        $service->flush();

        $this->assertSame('تغییر مستقیم', $service->get('general.app_name'));
    }
}
