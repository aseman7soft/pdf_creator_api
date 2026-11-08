<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $disk = (string) config('pdf.disk', 'local');
        $fonts = [
            [
                'handle' => 'vazirmatn-regular',
                'name' => 'وزیرمتن Regular',
                'source' => resource_path('fonts/Vazirmatn-Regular.woff2'),
                'ttf' => resource_path('fonts/Vazirmatn-Regular.ttf'),
            ],
            [
                'handle' => 'vazirmatn-bold',
                'name' => 'وزیرمتن Bold',
                'source' => resource_path('fonts/Vazirmatn-Bold.woff2'),
                'ttf' => resource_path('fonts/Vazirmatn-Bold.ttf'),
            ],
        ];

        foreach ($fonts as $font) {
            if (! is_file($font['source'])) {
                if (is_file($font['ttf'])) {
                    $font['source'] = $font['ttf'];
                } else {
                    $this->command?->warn("Font file missing: {$font['source']}");

                    continue;
                }
            }

            $extension = strtolower(pathinfo($font['source'], PATHINFO_EXTENSION));
            $path = 'assets/fonts/'.$font['handle'].'.'.$extension;
            $contents = (string) file_get_contents($font['source']);
            Storage::disk($disk)->put($path, $contents);

            Asset::query()->updateOrCreate(
                ['handle' => $font['handle']],
                [
                    'name' => $font['name'],
                    'type' => AssetType::Font,
                    'mime_type' => $extension === 'woff2' ? 'font/woff2' : 'font/ttf',
                    'extension' => $extension,
                    'size' => strlen($contents),
                    'disk' => $disk,
                    'path' => $path,
                    'checksum' => hash('sha256', $contents),
                    'description' => 'فونت فارسی Vazirmatn برای رندر صحیح متون فارسی در PDF',
                ],
            );
        }
    }
}
