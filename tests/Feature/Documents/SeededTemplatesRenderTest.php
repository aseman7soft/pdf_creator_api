<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Template;
use App\Services\Documents\DocumentGenerationService;
use App\Services\Templates\TemplateRenderService;
use App\Services\Templates\TemplateSchemaValidator;
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
        $this->assertStringContainsString('واچر هتل', $hotelRendered->html);
        $this->assertStringContainsString('۱۲٬۵۰۰٬۰۰۰', $hotelRendered->html);
        $this->assertStringContainsString('<svg', $hotelRendered->html);
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

    public function test_accommodation_and_flight_samples_render_repeated_rows(): void
    {
        $renderer = $this->app->make(TemplateRenderService::class);
        $validator = $this->app->make(TemplateSchemaValidator::class);

        $accommodation = Template::query()->where('slug', 'accommodation-voucher')->firstOrFail();
        $legacy = Template::query()->where('slug', 'hotel-voucher')->firstOrFail();
        $flight = Template::query()->where('slug', 'flight-ticket')->firstOrFail();

        $hotel = $this->sample('accommodation-hotel.json');
        $hotelHtml = $renderer->render($accommodation, $validator->validate($accommodation, $hotel))->html;
        $this->assertStringContainsString('واچر هتل', $hotelHtml);
        $this->assertStringContainsString('هتل اسپیناس پالاس', $hotelHtml);
        $this->assertStringContainsString('اتاق توئین', $hotelHtml);
        $this->assertStringContainsString('کیان رضایی', $hotelHtml);
        $this->assertStringContainsString('VCH-88421', $hotelHtml);
        $this->assertStringContainsString('R-55210', $hotelHtml);
        $this->assertStringContainsString('INV-1405-2201', $hotelHtml);
        $this->assertStringNotContainsString('SNP-993311', $hotelHtml);
        $this->assertStringNotContainsString('hotel_hub', $hotelHtml);
        $this->assertStringNotContainsString('hb_8f3a21', $hotelHtml);
        $this->assertStringNotContainsString('RM-1', $hotelHtml);
        $this->assertStringNotContainsString('تأمین‌کننده', $hotelHtml);
        $this->assertStringContainsString('۱۸٬۰۰۰٬۰۰۰', $hotelHtml);
        $this->assertStringContainsString('<svg', $hotelHtml);
        $this->assertStringNotContainsString('asset://', $hotelHtml);

        $legacyHtml = $renderer->render($legacy, $validator->validate($legacy, $this->hotelData()))->html;
        $this->assertStringContainsString('واچر هتل', $legacyHtml);
        $this->assertStringContainsString('هتل پارسیان', $legacyHtml);
        $this->assertStringContainsString('BK-100', $legacyHtml);

        $villa = $this->sample('accommodation-villa.json');
        $villaHtml = $renderer->render($accommodation, $validator->validate($accommodation, $villa))->html;
        $this->assertStringContainsString('واچر ویلا', $villaHtml);
        $this->assertStringContainsString('ویلای ساحلی نخل', $villaHtml);
        $this->assertStringContainsString('سارا محمدی', $villaHtml);
        $this->assertStringNotContainsString('واچر هتل', $villaHtml);

        $residence = $this->sample('accommodation-residence.json');
        $residenceHtml = $renderer->render($accommodation, $validator->validate($accommodation, $residence))->html;
        $this->assertStringContainsString('واچر اقامتگاه', $residenceHtml);
        $this->assertStringContainsString('نقش‌جهان', $residenceHtml);

        $oneWay = $this->sample('flight-one-way.json');
        $oneWayHtml = $renderer->render($flight, $validator->validate($flight, $oneWay))->html;
        $this->assertStringContainsString('بلیط هواپیما', $oneWayHtml);
        $this->assertStringContainsString('یک‌طرفه', $oneWayHtml);
        $this->assertStringContainsString('KIS7H2', $oneWayHtml);
        $this->assertStringContainsString('TRK-77821', $oneWayHtml);
        $this->assertStringContainsString('R-57011', $oneWayHtml);
        $this->assertStringNotContainsString('fl_19ab', $oneWayHtml);
        $this->assertStringNotContainsString('flight_hub', $oneWayHtml);
        $this->assertStringContainsString('Y9 712', $oneWayHtml);
        $this->assertStringContainsString('نگار احمدی', $oneWayHtml);
        $this->assertStringContainsString('آرمین احمدی', $oneWayHtml);
        $this->assertStringContainsString('کیش', $oneWayHtml);
        $this->assertStringContainsString('<svg', $oneWayHtml);

        $round = $this->sample('flight-round-trip.json');
        $roundHtml = $renderer->render($flight, $validator->validate($flight, $round))->html;
        $this->assertStringContainsString('رفت و برگشت', $roundHtml);
        $this->assertStringContainsString('TRK-88002', $roundHtml);
        $this->assertStringContainsString('R-57088', $roundHtml);
        $this->assertStringNotContainsString('fl_77cd', $roundHtml);
        $this->assertStringNotContainsString('flight_hub', $roundHtml);
        $this->assertStringContainsString('آنکارا', $roundHtml);
        $this->assertStringContainsString('استانبول', $roundHtml);
        $this->assertStringContainsString('W5 073', $roundHtml);
        $this->assertStringContainsString('نیا کاظمی', $roundHtml);
        $this->assertStringContainsString('برگشت', $roundHtml);
        $this->assertStringContainsString('۱۸٬۶۵۰٬۰۰۰', $roundHtml);

        $snapshot = [
            'pnr' => 'SYZ1',
            'status' => 'issued',
            'trip_type' => 'one_way',
            'passengers' => [
                ['firstName' => 'رضا', 'lastName' => 'نعمتی', 'nationalId' => '123', 'passengerType' => 'ADT'],
            ],
            'segments' => [],
            'route_snapshot' => [
                'summary' => 'شیراز به مشهد',
                'criteria' => ['tripType' => 'one_way', 'origin' => 'SYZ', 'destination' => 'MHD'],
                'card' => [
                    'airlineLabel' => 'ماهان',
                    'cabinClassLabel' => 'اکونومی',
                    'fareTypeLabel' => 'سیستمی',
                    'baggage' => '20 KG',
                    'sellTotal' => 2500000,
                    'legs' => [[
                        'origin' => 'SYZ',
                        'destination' => 'MHD',
                        'originCityLabel' => 'شیراز',
                        'destinationCityLabel' => 'مشهد',
                        'departureTime' => '07:00',
                        'arrivalTime' => '08:20',
                        'departureDateLabel' => '۱ دی ۱۴۰۵',
                        'flightNumber' => 'W5 100',
                        'airlineLabel' => 'ماهان',
                        'cabinClassLabel' => 'اکونومی',
                        'baggage' => '20 KG',
                        'durationLabel' => '۱ ساعت و ۲۰ دقیقه',
                        'optionIndex' => 0,
                    ]],
                    'priceBreakdown' => [
                        ['label' => 'بزرگسال', 'passengerType' => 'ADT', 'quantity' => 1, 'totalFare' => 2500000],
                    ],
                ],
            ],
            'currency' => 'تومان',
        ];
        $snapshotHtml = $renderer->render($flight, $validator->validate($flight, $snapshot))->html;
        $this->assertStringContainsString('شیراز', $snapshotHtml);
        $this->assertStringContainsString('مشهد', $snapshotHtml);
        $this->assertStringContainsString('W5 100', $snapshotHtml);
        $this->assertStringContainsString('رضا نعمتی', $snapshotHtml);
        $this->assertStringContainsString('۲٬۵۰۰٬۰۰۰', $snapshotHtml);
    }

    public function test_flight_ticket_redesign_prints_one_page_per_passenger_segment_with_rule_boxes(): void
    {
        $renderer = $this->app->make(TemplateRenderService::class);
        $validator = $this->app->make(TemplateSchemaValidator::class);
        $flight = Template::query()->where('slug', 'flight-ticket')->firstOrFail();

        $segment = [
            'direction' => 'outbound',
            'airline' => 'هواپیمایی یزد',
            'airline_code' => 'DZD',
            'flight_number' => 'DZD9443',
            'aircraft' => 'BAe 146',
            'from_city_name' => 'تهران',
            'from_airport' => 'فرودگاه بین‌المللی مهرآباد',
            'from_code' => 'THR',
            'to_city_name' => 'یزد',
            'to_airport' => 'فرودگاه شهید صدوقی',
            'to_code' => 'AZD',
            'depart_date' => '2026-11-29',
            'depart_date_label' => 'یکشنبه، 8 آذر، 1405',
            'depart_time' => '15:20',
            'arrival_time' => '16:35',
            'baggage' => '10 کیلوگرم',
        ];
        $data = [
            'pnr' => '42809320',
            're_code' => '42809320',
            'hub_public_id' => 'FH-HIDDEN-1',
            'supplier_code' => 'SUP-HIDDEN-1',
            'trip_type' => 'round_trip',
            'passengers' => [
                ['firstName' => 'نمونه', 'lastName' => 'یک', 'codeMeli' => '0012345678', 'age_group_label' => 'بزرگسال'],
                ['firstName' => 'نمونه', 'lastName' => 'دو', 'codeMeli' => '0012345679', 'age_group_label' => 'کودک'],
            ],
            'segments' => [$segment, array_merge($segment, ['direction' => 'return', 'flight_number' => 'DZD9444', 'from_city_name' => 'یزد', 'to_city_name' => 'تهران'])],
            'total_price' => 126280000,
            'currency' => 'ریال',
            'baggage_rules' => ['پرواز DZD9443: بار مجاز 10 کیلوگرم'],
            'cancellation_policies' => [
                ['description' => 'از لحظه صدور تا 72 ساعت مانده به پرواز', 'adultPenaltyPercentage' => 30, 'childPenaltyPercentage' => 30, 'infantPenaltyPercentage' => 30],
            ],
            'general_rules' => ['نوع نرخ: سیستمی'],
        ];

        $html = $renderer->render($flight, $validator->validate($flight, $data))->html;

        $this->assertSame(4, substr_count($html, '<div class="ticket">'));
        $this->assertStringContainsString('بلیط رفت', $html);
        $this->assertStringContainsString('بلیط برگشت', $html);
        $this->assertStringContainsString('فرودگاه بین‌المللی مهرآباد', $html);
        $this->assertStringContainsString('هواپیمایی یزد', $html);
        $this->assertStringContainsString('BAe 146', $html);
        $this->assertStringContainsString('2026-11-29', $html);
        $this->assertStringContainsString('قوانین بار', $html);
        $this->assertStringContainsString('قوانین کنسلی', $html);
        $this->assertStringContainsString('قوانین و مقررات', $html);
        $this->assertStringContainsString('نکات پرواز', $html);
        $this->assertStringContainsString('۱۲٬۶۲۸٬۰۰۰ تومان', $html);
        $this->assertStringContainsString('۱۲۶٬۲۸۰٬۰۰۰ ریال', $html);
        $this->assertStringContainsString('۳۰٪', $html);
        $this->assertStringNotContainsString('FH-HIDDEN-1', $html);
        $this->assertStringNotContainsString('SUP-HIDDEN-1', $html);
    }

    public function test_show_abs_reservation_templates_hide_supplier_codes(): void
    {
        $renderer = $this->app->make(TemplateRenderService::class);
        $validator = $this->app->make(TemplateSchemaValidator::class);

        $tour = $this->renderSample($renderer, $validator, 'tour-voucher', 'tour-voucher.json');
        $this->assertStringContainsString('واچر تور داخلی', $tour);
        $this->assertStringContainsString('تور کیش نوروز', $tour);
        $this->assertStringContainsString('42764', $tour);
        $this->assertStringContainsString('هتل داریوش کیش', $tour);
        $this->assertStringContainsString('W5 1120', $tour);
        $this->assertStringContainsString('بیمه مسافرتی داخلی', $tour);
        $this->assertStringContainsString('INS-42764', $tour);
        $this->assertStringContainsString('مهدی کرمی', $tour);
        $this->assertStringContainsString('گشت جزیره', $tour);
        $this->assertStringContainsString('رضا نوری', $tour);
        $this->assertStringContainsString('۱۸۶٬۵۰۰٬۰۰۰', $tour);
        $this->assertStringContainsString('<svg', $tour);
        $this->assertStringNotContainsString('tour_hub', $tour);
        $this->assertStringNotContainsString('SUP-TOUR-1', $tour);
        $this->assertStringNotContainsString('tb_91aa', $tour);
        $this->assertStringNotContainsString('RM-SUP-9', $tour);
        $this->assertStringNotContainsString('تأمین‌کننده', $tour);

        $insurance = $this->renderSample($renderer, $validator, 'insurance-voucher', 'insurance-voucher.json');
        $this->assertStringContainsString('واچر بیمه', $insurance);
        $this->assertStringContainsString('طرح طلایی ۳۰ روزه', $insurance);
        $this->assertStringContainsString('INS-51002', $insurance);
        $this->assertStringContainsString('سارا محمدی', $insurance);
        $this->assertStringContainsString('کیان محمدی', $insurance);
        $this->assertStringContainsString('۹٬۸۰۰٬۰۰۰', $insurance);
        $this->assertStringNotContainsString('insure_hub', $insurance);
        $this->assertStringNotContainsString('POL-SUP-77', $insurance);
        $this->assertStringNotContainsString('in_44ab', $insurance);

        $visa = $this->renderSample($renderer, $validator, 'visa-voucher', 'visa-voucher.json');
        $this->assertStringContainsString('واچر ویزا', $visa);
        $this->assertStringContainsString('ویزای توریستی دبی', $visa);
        $this->assertStringContainsString('VISA-61018', $visa);
        $this->assertStringContainsString('A12345678', $visa);
        $this->assertStringContainsString('حسین عباسی', $visa);
        $this->assertStringNotContainsString('visa_hub', $visa);
        $this->assertStringNotContainsString('VIS-SUP-3', $visa);
        $this->assertStringNotContainsString('vs_10cd', $visa);

        $cip = $this->renderSample($renderer, $validator, 'cip-voucher', 'cip-voucher.json');
        $this->assertStringContainsString('واچر تشریفات', $cip);
        $this->assertStringContainsString('CIP-2044', $cip);
        $this->assertStringContainsString('IR 455', $cip);
        $this->assertStringContainsString('اصفهان', $cip);
        $this->assertStringContainsString('همراه مشایعت', $cip);
        $this->assertStringContainsString('الهام رضایی', $cip);
        $this->assertStringNotContainsString('cip_hub', $cip);
        $this->assertStringNotContainsString('CIP-SUP-8', $cip);
        $this->assertStringNotContainsString('cp_77ee', $cip);

        $mixed = $this->renderSample($renderer, $validator, 'reservation-document', 'reservation-document.json');
        $this->assertStringContainsString('سند رزرو', $mixed);
        $this->assertStringContainsString('کنسلی رزرو', $mixed);
        $this->assertStringContainsString('70031', $mixed);
        $this->assertStringContainsString('جریمه کنسلی مطابق قرارداد', $mixed);
        $this->assertStringContainsString('بهروز نادری', $mixed);
        $this->assertStringContainsString('0060000031', $mixed);
        $this->assertStringContainsString('۱۲٬۰۰۰٬۰۰۰', $mixed);
        $this->assertStringNotContainsString('cancel_hub', $mixed);
        $this->assertStringNotContainsString('CX-SUP-1', $mixed);
        $this->assertStringNotContainsString('cx_19ff', $mixed);
    }

    /**
     * @return array<string, mixed>
     */
    private function renderSample(TemplateRenderService $renderer, TemplateSchemaValidator $validator, string $slug, string $sample): string
    {
        $template = Template::query()->where('slug', $slug)->firstOrFail();
        $html = $renderer->render($template, $validator->validate($template, $this->sample($sample)))->html;
        $this->assertStringNotContainsString('asset://', $html);

        return $html;
    }

    private function sample(string $filename): array
    {
        $decoded = json_decode((string) file_get_contents(resource_path('templates/samples/'.$filename)), true);

        $this->assertIsArray($decoded);

        return $decoded;
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
