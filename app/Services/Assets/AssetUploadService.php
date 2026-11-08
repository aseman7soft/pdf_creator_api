<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validated storage of panel-uploaded assets (fonts, images, css).
 *
 * The whitelist is intentionally strict: PHP/SVG/HTML/JS files (and anything
 * else that could be interpreted by a web server) are always rejected, both by
 * content based MIME detection and by the original file extension.
 */
class AssetUploadService
{
    /**
     * Content based MIME types accepted per asset type.
     *
     * @var array<string, list<string>>
     */
    public const MIME_WHITELIST = [
        'font' => [
            'font/woff2',
            'font/woff',
            'font/ttf',
            'font/otf',
            'font/sfnt',
            'application/font-woff',
            'application/font-sfnt',
            'application/x-font-ttf',
            'application/x-font-woff',
            'application/x-font-opentype',
            'application/vnd.ms-opentype',
            'application/octet-stream',
        ],
        'image' => [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
        ],
        'css' => [
            'text/css',
            'text/plain',
        ],
        'other' => [
            'application/pdf',
            'application/zip',
            'application/json',
            'text/plain',
        ],
    ];

    /**
     * Original extensions accepted per asset type.
     *
     * @var array<string, list<string>>
     */
    public const EXTENSION_WHITELIST = [
        'font' => ['woff2', 'woff', 'ttf', 'otf'],
        'image' => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        'css' => ['css'],
        'other' => ['pdf', 'zip', 'json', 'txt'],
    ];

    /**
     * Extensions that are never accepted, whatever the detected mime is.
     *
     * @var list<string>
     */
    public const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht',
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'js', 'mjs', 'cjs',
        'sh', 'bash', 'exe', 'dll', 'bat', 'cmd', 'ps1', 'jsp', 'asp', 'aspx',
        'cgi', 'pl', 'py', 'rb', 'htaccess', 'ini',
    ];

    public function maxUploadKb(): int
    {
        return max(1, (int) setting('security.max_upload_kb', config('pdf.max_upload_kb', 5120)));
    }

    /**
     * @throws ValidationException
     */
    public function store(
        UploadedFile $file,
        AssetType $type,
        ?User $user = null,
        ?string $name = null,
        ?string $description = null,
    ): Asset {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'بارگذاری فایل ناموفق بود. لطفاً دوباره تلاش کنید.',
            ]);
        }

        $extension = $this->resolveExtension($file, $type);

        if ($file->getSize() !== false && $file->getSize() > $this->maxUploadKb() * 1024) {
            throw ValidationException::withMessages([
                'file' => 'حجم فایل نباید بیشتر از '.fa_num($this->maxUploadKb()).' کیلوبایت باشد.',
            ]);
        }

        $contents = (string) file_get_contents((string) $file->getRealPath());
        $displayName = trim((string) ($name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));

        if ($displayName === '') {
            $displayName = $type->label().' '.now()->format('Y-m-d H:i');
        }

        $handle = $this->uniqueHandle($displayName, $type);
        $path = 'assets/'.$type->value.'/'.$handle.'.'.$extension;
        $disk = (string) config('pdf.disk', 'local');

        Storage::disk($disk)->put($path, $contents);

        return Asset::query()->create([
            'handle' => $handle,
            'name' => Str::limit($displayName, 140, ''),
            'type' => $type,
            'mime_type' => $this->detectMime($file, $extension, $type),
            'extension' => $extension,
            'size' => strlen($contents),
            'disk' => $disk,
            'path' => $path,
            'checksum' => hash('sha256', $contents),
            'metadata' => $this->metadata($file, $extension, $type),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'uploaded_by' => $user?->getKey(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function delete(Asset $asset): void
    {
        try {
            $disk = Storage::disk($asset->diskName());

            if ($asset->path !== '' && $disk->exists($asset->path)) {
                $disk->delete($asset->path);
            }
        } finally {
            $asset->delete();
        }
    }

    /**
     * @throws ValidationException
     */
    private function resolveExtension(UploadedFile $file, AssetType $type): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));

        if ($extension === '') {
            $extension = (string) $file->guessExtension();
            $extension = strtolower($extension);
        }

        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        if ($extension === '' || in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'نوع فایل مجاز نیست. فایل‌های اجرایی، SVG و HTML قابل بارگذاری نیستند.',
            ]);
        }

        $allowedExtensions = self::EXTENSION_WHITELIST[$type->value] ?? [];

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'پسوند «'.$extension.'» برای نوع انتخاب‌شده مجاز نیست. پسوندهای مجاز: '
                    .implode('، ', $allowedExtensions).'.',
            ]);
        }

        $mime = (string) $file->getMimeType();
        $allowedMimes = self::MIME_WHITELIST[$type->value] ?? [];

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'نوع محتوای فایل ('.$mime.') با نوع انتخاب‌شده همخوانی ندارد.',
            ]);
        }

        return $extension;
    }

    private function detectMime(UploadedFile $file, string $extension, AssetType $type): string
    {
        $mime = (string) $file->getMimeType();

        if (in_array($mime, self::MIME_WHITELIST[$type->value] ?? [], true)) {
            return $mime;
        }

        return match ($extension) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'css' => 'text/css',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(UploadedFile $file, string $extension, AssetType $type): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'client_mime' => $file->getClientMimeType(),
        ];

        if ($type === AssetType::Image) {
            $path = (string) $file->getRealPath();
            $dimensions = @getimagesize($path);

            if (is_array($dimensions)) {
                $metadata['width'] = (int) $dimensions[0];
                $metadata['height'] = (int) $dimensions[1];
            }
        }

        return $metadata;
    }

    private function uniqueHandle(string $source, AssetType $type): string
    {
        $base = Str::slug($source, '-');

        if ($base === '') {
            // Persian-only names do not transliterate to ASCII; fall back to a
            // random but still human readable handle.
            $base = $type->value.'-'.Str::lower(Str::random(6));
        }

        $base = Str::limit(preg_replace('/[^a-z0-9\-]/', '', strtolower($base)) ?? '', 48, '') ?: 'asset';
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'asset';
        }

        do {
            $handle = $base.'-'.Str::lower(Str::random(5));
            $exists = Asset::query()->withTrashed()->where('handle', $handle)->exists();
        } while ($exists);

        return $handle;
    }
}
