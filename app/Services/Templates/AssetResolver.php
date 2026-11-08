<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Exceptions\TemplateRenderException;
use App\Models\Asset;

/**
 * Resolves `asset://handle` references to flattened attachment file names and
 * loads their binary contents from the asset storage disk.
 *
 * Every attachment is placed in the root of the Gotenberg request payload as
 * `{handle}.{extension}` (Gotenberg's Chromium form does not preserve paths).
 */
final class AssetResolver
{
    private const HANDLE_PATTERN = '/asset:\/\/([A-Za-z0-9][A-Za-z0-9._-]{0,127})/';

    /**
     * @var array<string, array{filename: string, contents: string}>
     */
    private array $cache = [];

    /**
     * Replace asset:// references in multiple sources at once.
     *
     * @param  array<string, string|null>  $sources
     * @return array{sources: array<string, string>, assets: array<string, string>}
     */
    public function resolveSources(array $sources): array
    {
        $resolved = [];
        $assets = [];

        foreach ($sources as $key => $source) {
            if ($source === null || $source === '') {
                $resolved[$key] = '';

                continue;
            }

            $contents = preg_replace_callback(
                self::HANDLE_PATTERN,
                function (array $matches) use (&$assets): string {
                    $asset = $this->load((string) $matches[1]);
                    $assets[$asset['filename']] = $asset['contents'];

                    return $asset['filename'];
                },
                $source,
            );

            if ($contents === null) {
                throw new TemplateRenderException('پردازش فایل‌های جانبی قالب ممکن نبود.');
            }

            $resolved[$key] = $contents;
        }

        return ['sources' => $resolved, 'assets' => $assets];
    }

    /**
     * Resolve a single handle (used for image variables whose value is asset://).
     *
     * @return array{filename: string, contents: string}
     */
    public function resolveHandle(string $handle): array
    {
        return $this->load($handle);
    }

    /**
     * @return array{filename: string, contents: string}
     */
    private function load(string $handle): array
    {
        if (isset($this->cache[$handle])) {
            return $this->cache[$handle];
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $handle) !== 1) {
            throw new TemplateRenderException('شناسه فایل جانبی قالب معتبر نیست.');
        }

        /** @var Asset|null $asset */
        $asset = Asset::query()->where('handle', $handle)->first();

        if (! $asset instanceof Asset) {
            throw new TemplateRenderException('فایل جانبی «'.$handle.'» یافت نشد.');
        }

        $contents = $asset->contents();

        if ($contents === null) {
            throw new TemplateRenderException('محتوای فایل جانبی «'.$handle.'» در دسترس نیست.');
        }

        $extension = $this->extension($asset);

        return $this->cache[$handle] = [
            'filename' => $handle.'.'.$extension,
            'contents' => $contents,
        ];
    }

    private function extension(Asset $asset): string
    {
        $extension = strtolower(trim((string) $asset->extension));

        if ($extension === '') {
            $extension = strtolower(pathinfo((string) $asset->path, PATHINFO_EXTENSION));
        }

        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            return 'bin';
        }

        return $extension;
    }
}
