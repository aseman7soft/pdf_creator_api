<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Exceptions\DocumentGenerationException;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\TestCase;

class DocumentStorageServiceTest extends TestCase
{
    use FakePdf;
    use RefreshDatabase;

    private DocumentStorageService $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->storage = $this->app->make(DocumentStorageService::class);
    }

    public function test_store_writes_pdf_to_private_dated_ulid_path(): void
    {
        $document = GeneratedDocument::factory()->create();
        $pdf = self::mockPdfBytes('STORAGE');

        $path = $this->storage->store($document, $pdf);

        $this->assertMatchesRegularExpression(
            '#^documents/\d{4}/\d{2}/[0-9A-HJKMNP-TV-Z]{26}\.pdf$#',
            $path,
        );
        $this->assertSame($path, 'documents/'.now()->format('Y').'/'.now()->format('m').'/'.$document->ulid.'.pdf');
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame($pdf, Storage::disk('local')->get($path));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_read_returns_stored_bytes(): void
    {
        $document = GeneratedDocument::factory()->create();
        $pdf = self::mockPdfBytes('READ');
        $document->forceFill(['file_path' => $this->storage->store($document, $pdf)])->save();

        $this->assertSame($pdf, $this->storage->read($document->refresh()));
    }

    public function test_exists_and_delete_operate_on_the_private_disk(): void
    {
        $document = GeneratedDocument::factory()->create();
        $path = $this->storage->store($document, self::mockPdfBytes());
        $document->forceFill(['file_path' => $path])->save();

        $this->assertTrue($this->storage->exists($document));
        $this->assertTrue($this->storage->delete($document));
        $this->assertFalse($this->storage->exists($document));
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertFalse($this->storage->delete($document));
    }

    public function test_absolute_path_points_inside_the_private_disk(): void
    {
        $document = GeneratedDocument::factory()->create();
        $path = $this->storage->store($document, self::mockPdfBytes());
        $document->forceFill(['file_path' => $path])->save();

        $absolute = $this->storage->absolutePath($document);
        $normalised = str_replace('\\', '/', $absolute);

        $this->assertStringEndsWith($path, $normalised);
        $this->assertStringNotContainsString('/public/', $normalised);
    }

    public function test_store_rejects_empty_contents(): void
    {
        $document = GeneratedDocument::factory()->create();

        try {
            $this->storage->store($document, '');
            $this->fail('Expected a storage error.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('STORAGE_ERROR', $exception->getErrorCode());
        }
    }

    public function test_read_requires_a_stored_file(): void
    {
        $document = GeneratedDocument::factory()->create();

        $this->expectException(DocumentGenerationException::class);

        $this->storage->read($document);
    }

    public function test_read_reports_missing_file(): void
    {
        $document = GeneratedDocument::factory()->create();
        $document->forceFill(['file_path' => 'documents/2026/01/missing.pdf'])->save();

        $this->expectException(DocumentGenerationException::class);

        $this->storage->read($document);
    }
}
