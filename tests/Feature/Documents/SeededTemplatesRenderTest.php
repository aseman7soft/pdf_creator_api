<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Template;
use App\Services\Documents\DocumentGenerationService;
use App\Services\Templates\TemplateRenderService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Support\FakePdf;
use Tests\TestCase;

/**
 * End-to-end rendering of every template shipped by TemplateSeeder, which is
 * the authoritative list of template syntax the engine must support.
 */
class SeededTemplatesRenderTest extends TestCase
{
    use FakePdf;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(AssetSeeder::class);
        $this->seed(TemplateSeeder::class);
    }

    public function test_every_seeded_template_renders_with_realistic_data(): void
    {
        $renderer = $this->app->make(TemplateRenderService::class);

        $hotel = Template::query()->where('slug', 'hotel-voucher')->firstOrFail();
        $hotelRendered = $renderer->render($hotel, $this->hotelData());

        $this->assertStringContainsString('هتل پارسیان', $hotelRendered->html);
        $this->assertStringContainsString('BK-100', $hotelRendered->html);
        $this->assertStringContainsString('علی', $hotelRendered->html);
        $this->assertStringContainsString('علی رضایی', $hotelRendered->html);
        $this->assertTrue(mb_check_encoding($hotelRendered->html, 'UTF-8'));
        // UTF-8 bytes of «علی» misread as Latin-1 must never appear in output.
        $this->assertStringNotContainsString('Ø¹Ù„ÛŒ', $hotelRendered->html);
        $this->assertStringContainsString('12,500,000', $hotelRendered->html);
        $this->assertStringNotContainsString('asset://', $hotelRendered->html);
        $this->assertArrayHasKey('vazirmatn-regular.woff2', $hotelRendered->assets);
        $this->assertArrayHasKey('vazirmatn-bold.woff2', $hotelRendered->assets);
        $this->assertNotNull($hotelRendered->footerHtml);
        $this->assertStringContainsString('pageNumber', (string) $hotelRendered->footerHtml);

        $invoice = Template::query()->where('slug', 'invoice-standard')->firstOrFail();
        $invoiceRendered = $renderer->render($invoice, $this->invoiceData());

        $this->assertStringContainsString('INV-2026-1', $invoiceRendered->html);
        $this->assertStringContainsString('2026-11-08', $invoiceRendered->html);
        $this->assertStringContainsString('Tour package', $invoiceRendered->html);
        $this->assertStringContainsString('3,000', $invoiceRendered->html);
        $this->assertStringContainsString('USD', $invoiceRendered->html);
        $this->assertStringNotContainsString('asset://', $invoiceRendered->html);

        $tour = Template::query()->where('slug', 'tour-itinerary')->firstOrFail();
        $tourRendered = $renderer->render($tour, $this->tourData());

        $this->assertStringContainsString('تور استانبول', $tourRendered->html);
        $this->assertStringContainsString('TR-900', $tourRendered->html);
        $this->assertStringContainsString('صبحانه', $tourRendered->html);
        $this->assertStringContainsString('خدمات شامل نمی‌شود', $tourRendered->html);
        $this->assertStringNotContainsString('asset://', $tourRendered->html);
        $this->assertNotNull($tourRendered->footerHtml);
    }

    public function test_seeded_templates_can_be_generated_end_to_end(): void
    {
        Http::fake(['*' => Http::response(self::mockPdfBytes('SEEDED'), 200)]);

        $service = $this->app->make(DocumentGenerationService::class);
        $hotel = Template::query()->where('slug', 'hotel-voucher')->firstOrFail();

        $document = $service->generateSync($hotel, $this->hotelData());

        $this->assertSame('completed', $document->status->value);
        $this->assertNotNull($document->file_path);
        $this->assertSame(self::mockPdfBytes('SEEDED'), Storage::disk('local')->get((string) $document->file_path));

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $indexHtml = '';

            foreach ($data as $part) {
                if (is_array($part) && ($part['filename'] ?? null) === 'index.html') {
                    $indexHtml = (string) $part['contents'];
                }
            }

            $this->assertStringContainsString('هتل پارسیان', $indexHtml);
            $this->assertStringContainsString('vazirmatn-regular.woff2', $indexHtml);

            return true;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function hotelData(): array
    {
        return [
            'guest_name' => 'علی رضایی',
            'hotel_name' => 'هتل پارسیان',
            'booking_reference' => 'BK-100',
            'check_in' => '2026-11-08',
            'check_out' => '2026-11-10',
            'room_count' => 2,
            'total_price' => 12500000,
            'currency' => 'IRR',
            'guests' => [
                ['name' => 'علی', 'passport' => 'P-1'],
                ['name' => 'مریم'],
            ],
            'notes' => 'لطفاً اتاق طبقه بالا.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceData(): array
    {
        return [
            'invoice_number' => 'INV-2026-1',
            'issue_date' => '2026-11-08',
            'due_date' => '2026-11-20',
            'customer_name' => 'Acme Travel Ltd',
            'customer_address' => "Tehran\nIran",
            'customer_email' => 'billing@acme.test',
            'company_name' => 'AS7TH Travel Group',
            'items' => [
                ['description' => 'Tour package', 'quantity' => 2, 'unit_price' => 1500, 'amount' => 3000],
            ],
            'subtotal' => 3000,
            'discount' => 100,
            'tax_rate' => 9,
            'tax' => 261,
            'total' => 3161,
            'currency' => 'USD',
            'status' => 'PAID',
            'notes' => 'Thank you.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tourData(): array
    {
        return [
            'tour_name' => 'تور استانبول',
            'tour_code' => 'TR-900',
            'start_date' => '2026-11-08',
            'end_date' => '2026-11-12',
            'traveler_count' => 12,
            'duration_days' => 5,
            'destination' => 'استانبول',
            'days' => [
                ['day_number' => 1, 'title' => 'ورود', 'description' => 'ورود به استانبول', 'meals' => 'صبحانه', 'hotel' => 'هتل مرمر'],
                ['day_number' => 2, 'title' => 'گشت شهری', 'description' => 'بازدید از مسجد سلطان‌احمد'],
            ],
            'includes' => ['صبحانه', 'ترانسفر فرودگاهی'],
            'excludes' => ['هزینه ویزا'],
            'emergency_contact' => '021-12345678',
            'terms' => 'شرایط لغو رزرو.',
        ];
    }
}
