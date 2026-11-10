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
            $this->putShared($disk, $path, $contents);

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

        $logoSource = resource_path('images/aseman-haftom-logo.png');
        if (is_file($logoSource)) {
            $contents = (string) file_get_contents($logoSource);
            $path = 'assets/images/aseman-haftom-logo.png';
            $this->putShared($disk, $path, $contents);
            Asset::query()->updateOrCreate(
                ['handle' => 'aseman-haftom-logo'],
                [
                    'name' => 'لوگوی آسمان هفتم',
                    'type' => AssetType::Image,
                    'mime_type' => 'image/png',
                    'extension' => 'png',
                    'size' => strlen($contents),
                    'disk' => $disk,
                    'path' => $path,
                    'checksum' => hash('sha256', $contents),
                    'description' => 'لوگوی رسمی آژانس مسافرتی آسمان هفتم برای واچر هتل',
                ],
            );
        } else {
            $this->command?->warn("Logo file missing: {$logoSource}");
        }
    }

    /**
     * Write an asset and make its directory group-traversable (02770) so
     * php-fpm (www-data) can read files created by the pdf-runner seeder
     * even when the process umask is 077.
     */
    private function putShared(string $disk, string $path, string $contents): void
    {
        $directory = dirname($path);
        if ($directory !== '.' && $directory !== '') {
            Storage::disk($disk)->makeDirectory($directory);
            $this->chmodIfPossible(Storage::disk($disk)->path($directory), 02770);
        }

        Storage::disk($disk)->put($path, $contents);
        $this->chmodIfPossible(Storage::disk($disk)->path($path), 0664);
    }

    private function chmodIfPossible(string $target, int $mode): void
    {
        if (is_dir($target) || is_file($target)) {
            @chmod($target, $mode);
        }
    }
}
