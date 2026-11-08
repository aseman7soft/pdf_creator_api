<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Typed, cached access to the settings table.
 *
 * Settings are stored as strings plus a type hint so the admin panel can render
 * the correct form control and the application receives properly cast values.
 */
class SettingsService
{
    private const CACHE_KEY = 'app.settings.v1';

    /**
     * Fallback values used when a setting is missing from the database.
     *
     * @var array<string, array{value: mixed, type: string, group: string, description: string, is_public: bool}>
     */
    private const DEFAULTS = [
        'general.app_name' => [
            'value' => 'سرویس مرکزی تولید اسناد',
            'type' => 'string',
            'group' => 'general',
            'description' => 'نام نمایشی سرویس در پنل و صفحات',
            'is_public' => true,
        ],
        'general.brand_color' => [
            'value' => '#0d6efd',
            'type' => 'string',
            'group' => 'general',
            'description' => 'رنگ اصلی برند (HEX)',
            'is_public' => true,
        ],
        'general.support_email' => [
            'value' => 'support@as7th.com',
            'type' => 'string',
            'group' => 'general',
            'description' => 'ایمیل پشتیبانی',
            'is_public' => false,
        ],
        'general.items_per_page' => [
            'value' => 20,
            'type' => 'integer',
            'group' => 'general',
            'description' => 'تعداد رکورد در هر صفحه لیست‌ها',
            'is_public' => false,
        ],
        'documents.retention_days' => [
            'value' => 30,
            'type' => 'integer',
            'group' => 'documents',
            'description' => 'مدت نگهداری فایل اسناد تولیدشده (روز)',
            'is_public' => false,
        ],
        'documents.default_format' => [
            'value' => 'A4',
            'type' => 'string',
            'group' => 'documents',
            'description' => 'اندازه صفحه پیش‌فرض',
            'is_public' => false,
        ],
        'documents.max_payload_kb' => [
            'value' => 1024,
            'type' => 'integer',
            'group' => 'documents',
            'description' => 'حداکثر حجم داده ورودی API (کیلوبایت)',
            'is_public' => false,
        ],
        'documents.signed_url_ttl' => [
            'value' => 30,
            'type' => 'integer',
            'group' => 'documents',
            'description' => 'اعتبار لینک امضاشده دانلود (دقیقه)',
            'is_public' => false,
        ],
        'api.rate_limit_per_minute' => [
            'value' => 60,
            'type' => 'integer',
            'group' => 'api',
            'description' => 'سقف پیش‌فرض درخواست در دقیقه برای هر کلید',
            'is_public' => false,
        ],
        'api.idempotency_ttl_hours' => [
            'value' => 24,
            'type' => 'integer',
            'group' => 'api',
            'description' => 'مدت اعتبار کلید Idempotency (ساعت)',
            'is_public' => false,
        ],
        'gotenberg.url' => [
            'value' => null,
            'type' => 'string',
            'group' => 'gotenberg',
            'description' => 'آدرس Gotenberg (خالی = مقدار فایل env)',
            'is_public' => false,
        ],
        'gotenberg.timeout' => [
            'value' => 90,
            'type' => 'integer',
            'group' => 'gotenberg',
            'description' => 'Timeout تولید PDF (ثانیه)',
            'is_public' => false,
        ],
        'security.allow_remote_images' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'security',
            'description' => 'اجازه دریافت تصاویر اینترنتی برای متغیرهای تصویر',
            'is_public' => false,
        ],
        'security.max_upload_kb' => [
            'value' => 5120,
            'type' => 'integer',
            'group' => 'security',
            'description' => 'حداکثر حجم آپلود فایل جانبی (کیلوبایت)',
            'is_public' => false,
        ],
        'queue.name' => [
            'value' => 'documents',
            'type' => 'string',
            'group' => 'queue',
            'description' => 'نام صف پردازش اسناد',
            'is_public' => false,
        ],
        'queue.tries' => [
            'value' => 3,
            'type' => 'integer',
            'group' => 'queue',
            'description' => 'حداکثر تلاش مجدد Job',
            'is_public' => false,
        ],
        'queue.timeout' => [
            'value' => 180,
            'type' => 'integer',
            'group' => 'queue',
            'description' => 'حداکثر زمان اجرای Job (ثانیه)',
            'is_public' => false,
        ],
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            $settings = Cache::remember(self::CACHE_KEY, 600, function (): array {
                return Setting::query()
                    ->get()
                    ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->castedValue()])
                    ->all();
            });
        } catch (QueryException) {
            // The settings table may not exist yet (fresh install / migrations).
            $settings = [];
        }

        return $this->memo = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        if (array_key_exists($key, self::DEFAULTS)) {
            return self::DEFAULTS[$key]['value'];
        }

        return $default;
    }

    public function set(string $key, mixed $value, ?string $type = null, ?string $group = null, ?string $description = null, bool $isPublic = false): Setting
    {
        $type ??= $this->inferType($value);
        $group ??= str_contains($key, '.') ? explode('.', $key, 2)[0] : 'general';
        $description ??= self::DEFAULTS[$key]['description'] ?? null;
        $isPublic = $isPublic || (self::DEFAULTS[$key]['is_public'] ?? false);

        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'value' => $this->serialize($value, $type),
                'description' => $description,
                'is_public' => $isPublic,
            ],
        );

        $this->flush();

        return $setting;
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach (self::DEFAULTS as $key => $definition) {
            $groups[$definition['group']][$key] = array_merge($definition, [
                'value' => $this->get($key, $definition['value']),
            ]);
        }

        return $groups;
    }

    /**
     * @return array<string, array{value: mixed, type: string, group: string, description: string, is_public: bool}>
     */
    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
