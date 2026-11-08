<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DocumentGenerationException;
use App\Models\GeneratedDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Private storage for generated PDF files.
 *
 * Paths always live on the configured private disk under
 * `{pdf.document_path}/{YYYY}/{MM}/{ulid}.pdf` and are never exposed through
 * the public web root; downloads go through controllers/signed URLs.
 */
final class DocumentStorageService
{
    public function diskName(): string
    {
        return (string) config('pdf.disk', 'local');
    }

    public function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->diskName());

        return $disk;
    }

    /**
     * Persist PDF contents and return the relative storage path.
     *
     * @throws DocumentGenerationException
     */
    public function store(GeneratedDocument $document, string $contents): string
    {
        $path = $this->pathFor($document);

        if ($contents === '') {
            throw DocumentGenerationException::storageError('محتوای فایل PDF خالی است و ذخیره نشد.');
        }

        $stored = $this->disk()->put($path, $contents);

        if ($stored === false || ! $this->disk()->exists($path)) {
            throw DocumentGenerationException::storageError('ذخیره‌سازی فایل PDF در فضای خصوصی ناموفق بود.');
        }

        return $path;
    }

    /**
     * @throws DocumentGenerationException
     */
    public function read(GeneratedDocument $document): string
    {
        $path = $this->pathOrFail($document);

        if (! $this->disk()->exists($path)) {
            throw DocumentGenerationException::storageError('فایل PDF سند در فضای ذخیره‌سازی یافت نشد.');
        }

        $contents = $this->disk()->get($path);

        if (! is_string($contents) || $contents === '') {
            throw DocumentGenerationException::storageError('خواندن فایل PDF سند ناموفق بود.');
        }

        return $contents;
    }

    public function exists(GeneratedDocument $document): bool
    {
        $path = $document->file_path;

        if (! is_string($path) || $path === '') {
            return false;
        }

        return $this->disk()->exists($path);
    }

    public function delete(GeneratedDocument $document): bool
    {
        $path = $document->file_path;

        if (! is_string($path) || $path === '') {
            return false;
        }

        if (! $this->disk()->exists($path)) {
            return false;
        }

        return (bool) $this->disk()->delete($path);
    }

    /**
     * @throws DocumentGenerationException
     */
    public function absolutePath(GeneratedDocument $document): string
    {
        $path = $this->pathOrFail($document);

        return $this->disk()->path($path);
    }

    public function pathFor(GeneratedDocument $document): string
    {
        $base = trim((string) config('pdf.document_path', 'documents'), '/');
        $ulid = (string) $document->ulid;

        if ($base === '') {
            $base = 'documents';
        }

        if ($ulid === '') {
            throw DocumentGenerationException::storageError('شناسه یکتای سند برای مسیر ذخیره‌سازی موجود نیست.');
        }

        return $base.'/'.now()->format('Y').'/'.now()->format('m').'/'.$ulid.'.pdf';
    }

    /**
     * @throws DocumentGenerationException
     */
    private function pathOrFail(GeneratedDocument $document): string
    {
        $path = $document->file_path;

        if (! is_string($path) || $path === '') {
            throw DocumentGenerationException::storageError('مسیر فایل PDF برای این سند ثبت نشده است.');
        }

        return $path;
    }
}
