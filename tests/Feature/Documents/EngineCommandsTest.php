<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\TestCase;

class EngineCommandsTest extends TestCase
{
    use FakePdf;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'pdf.gotenberg.url' => 'http://127.0.0.1:3000',
            'pdf.gotenberg.health_timeout' => 2,
        ]);
    }

    public function test_pdf_health_reports_ok_when_all_critical_services_are_available(): void
    {
        Http::fake(['*/health' => Http::response('OK', 200)]);

        $this->artisan('pdf:health')
            ->expectsOutputToContain('Gotenberg')
            ->expectsOutputToContain('پایگاه‌داده')
            ->expectsOutputToContain('ذخیره‌سازی')
            ->assertExitCode(0);
    }

    public function test_pdf_health_json_output_is_structured(): void
    {
        Http::fake(['*/health' => Http::response('OK', 200)]);

        $exitCode = Artisan::call('pdf:health', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode(trim($output), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok']);
        $this->assertTrue($decoded['checks']['database']['ok']);
        $this->assertTrue($decoded['checks']['storage']['ok']);
        $this->assertTrue($decoded['checks']['gotenberg']['ok']);
        $this->assertArrayHasKey('queue', $decoded);
    }

    public function test_pdf_health_exits_non_zero_when_gotenberg_is_down(): void
    {
        Http::fake(['*/health' => Http::response('down', 500)]);

        $this->artisan('pdf:health')
            ->expectsOutputToContain('ناسالم')
            ->assertExitCode(1);
    }

    public function test_documents_prune_dry_run_does_not_touch_files_or_rows(): void
    {
        $document = $this->expiredDocument();

        $this->artisan('documents:prune', ['--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists((string) $document->file_path));
        $this->assertNotSoftDeleted($document);
    }

    public function test_documents_prune_deletes_expired_files_and_soft_deletes_rows(): void
    {
        $expired = $this->expiredDocument();
        $active = $this->activeDocument();

        $this->artisan('documents:prune')
            ->expectsOutputToContain('پاک‌سازی')
            ->assertExitCode(0);

        $this->assertFalse(Storage::disk('local')->exists((string) $expired->file_path));
        $this->assertSoftDeleted($expired);

        $this->assertTrue(Storage::disk('local')->exists((string) $active->file_path));
        $this->assertNotSoftDeleted($active);
    }

    public function test_documents_prune_respects_days_option_over_generated_at(): void
    {
        $document = $this->documentWithGeneratedAt(now()->subDays(5));
        $path = (string) $document->file_path;

        $this->artisan('documents:prune', ['--days' => 10])->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertNotSoftDeleted($document);

        $this->artisan('documents:prune', ['--days' => 3])->assertExitCode(0);

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSoftDeleted($document);
    }

    public function test_documents_prune_skips_documents_that_are_still_processing(): void
    {
        $document = $this->expiredDocument();
        $document->forceFill(['status' => 'processing'])->save();

        $this->artisan('documents:prune')->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists((string) $document->file_path));
        $this->assertNotSoftDeleted($document);
    }

    private function expiredDocument(): GeneratedDocument
    {
        $document = GeneratedDocument::factory()->completed()->create([
            'expires_at' => now()->subDay(),
            'generated_at' => now()->subDays(40),
        ]);

        $path = $this->app->make(DocumentStorageService::class)->store($document, self::mockPdfBytes('PRUNE'));
        $document->forceFill(['file_path' => $path])->save();

        return $document->refresh();
    }

    private function activeDocument(): GeneratedDocument
    {
        $document = GeneratedDocument::factory()->completed()->create([
            'expires_at' => now()->addDays(10),
            'generated_at' => now()->subDay(),
        ]);

        $path = $this->app->make(DocumentStorageService::class)->store($document, self::mockPdfBytes('KEEP'));
        $document->forceFill(['file_path' => $path])->save();

        return $document->refresh();
    }

    private function documentWithGeneratedAt(\DateTimeInterface $generatedAt): GeneratedDocument
    {
        $document = GeneratedDocument::factory()->completed()->create([
            'expires_at' => null,
            'generated_at' => $generatedAt,
        ]);

        $path = $this->app->make(DocumentStorageService::class)->store($document, self::mockPdfBytes('AGE'));
        $document->forceFill(['file_path' => $path])->save();

        return $document->refresh();
    }
}
