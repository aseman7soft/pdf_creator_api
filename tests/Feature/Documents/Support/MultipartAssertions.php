<?php

declare(strict_types=1);

namespace Tests\Feature\Documents\Support;

use Illuminate\Http\Client\Request;

trait MultipartAssertions
{
    /**
     * @return array<string, string>
     */
    protected function multipartFields(Request $request): array
    {
        $fields = [];

        foreach ($request->data() as $part) {
            if (is_array($part) && isset($part['name'], $part['contents']) && ! isset($part['filename'])) {
                $fields[(string) $part['name']] = (string) $part['contents'];
            }
        }

        return $fields;
    }

    /**
     * @return list<array{name: string, contents: string, filename: string}>
     */
    protected function multipartFiles(Request $request): array
    {
        $files = [];

        foreach ($request->data() as $part) {
            if (is_array($part) && isset($part['name'], $part['contents'], $part['filename'])) {
                $files[] = [
                    'name' => (string) $part['name'],
                    'contents' => (string) $part['contents'],
                    'filename' => (string) $part['filename'],
                ];
            }
        }

        return $files;
    }

    protected function multipartFileContents(Request $request, string $filename): ?string
    {
        foreach ($this->multipartFiles($request) as $file) {
            if ($file['filename'] === $filename) {
                return $file['contents'];
            }
        }

        return null;
    }
}
