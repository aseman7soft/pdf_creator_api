<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Template;
use Database\Seeders\Support\TravelDocumentCatalog;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTravelDocuments();
        $this->seedShowAbsDocuments();
        $this->seedInvoice();
        $this->seedTourItinerary();
    }

    /**
     * Upsert the accommodation voucher (hotel-voucher + accommodation-voucher)
     * and the flight ticket. Safe to run from a migration on deploy.
     */
    public function seedTravelDocuments(): void
    {
        $this->seedAccommodationVoucher(
            'hotel-voucher',
            'واچر اقامت',
            'hotel',
            'واچر اقامت آسمان هفتم برای هتل، اقامتگاه و ویلا. با payload قبلی hotel-voucher سازگار است.',
        );
        $this->seedAccommodationVoucher(
            'accommodation-voucher',
            'واچر اقامت (هتل / اقامتگاه / ویلا)',
            'accommodation',
            'قالب مشترک واچر هتل، اقامتگاه و ویلا. عنوان با accommodation_type عوض می‌شود.',
        );
        $this->seedFlightTicket();
    }

    /**
     * Upsert the show_abs / reservation-v3 documents (tour, insurance, visa,
     * CIP, and the mixed reservation sheet). Safe to run from a migration.
     */
    public function seedShowAbsDocuments(): void
    {
        $this->seedDocument(
            'tour-voucher',
            'واچر تور',
            'tour',
            'واچر تور نسخه ۳ (داخلی، خارجی، یک‌روزه) با اقامت، پرواز و بیمهٔ همراه.',
            'tour-voucher.html',
            TravelDocumentCatalog::tourVoucherVariables(),
        );
        $this->seedDocument(
            'insurance-voucher',
            'واچر بیمه',
            'insurance',
            'بیمه‌نامهٔ مسافرتی رزرو abs_insure.',
            'insurance-voucher.html',
            TravelDocumentCatalog::insuranceVariables(),
        );
        $this->seedDocument(
            'visa-voucher',
            'واچر ویزا',
            'visa',
            'سند خدمات ویزا برای رزرو abs_visa.',
            'visa-voucher.html',
            TravelDocumentCatalog::visaVariables(),
        );
        $this->seedDocument(
            'cip-voucher',
            'واچر CIP',
            'cip',
            'واچر تشریفات فرودگاهی. جدا از show_abs؛ نوع‌های cip_* .',
            'cip-voucher.html',
            TravelDocumentCatalog::cipVariables(),
        );
        $this->seedDocument(
            'reservation-document',
            'سند رزرو',
            'reservation',
            'سند ترکیبی برای کنسلی، نوع ناشناخته، یا سبد چندخدمتی که تور واحد نیست.',
            'reservation-document.html',
            TravelDocumentCatalog::reservationDocumentVariables(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     */
    private function seedDocument(string $slug, string $name, string $category, string $description, string $htmlFile, array $variables): void
    {
        $template = Template::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'html' => TravelDocumentCatalog::html($htmlFile),
                'footer_html' => TravelDocumentCatalog::footer(),
                'is_active' => true,
                'locale' => 'fa',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 16, 'right' => 10, 'bottom' => 16, 'left' => 10],
                'pdf_options' => ['print_background' => true, 'scale' => 1.0, 'display_header_footer' => true],
            ],
        );

        $this->syncVariables($template, $variables);
    }

    private function seedAccommodationVoucher(string $slug, string $name, string $category, string $description): void
    {
        $template = Template::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'html' => TravelDocumentCatalog::html('accommodation-voucher.html'),
                'footer_html' => TravelDocumentCatalog::footer(),
                'is_active' => true,
                'locale' => 'fa',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 16, 'right' => 10, 'bottom' => 16, 'left' => 10],
                'pdf_options' => ['print_background' => true, 'scale' => 1.0, 'display_header_footer' => true],
            ],
        );

        $this->syncVariables($template, TravelDocumentCatalog::accommodationVariables());
    }

    private function seedFlightTicket(): void
    {
        $template = Template::query()->updateOrCreate(
            ['slug' => 'flight-ticket'],
            [
                'name' => 'بلیط هواپیما',
                'category' => 'flight',
                'description' => 'بلیط الکترونیک پرواز آسمان هفتم؛ یک‌طرفه و رفت‌وبرگشت، چندمسافر و چندسگمنت.',
                'html' => TravelDocumentCatalog::html('flight-ticket.html'),
                'footer_html' => TravelDocumentCatalog::footer(),
                'is_active' => true,
                'locale' => 'fa',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 16, 'right' => 10, 'bottom' => 16, 'left' => 10],
                'pdf_options' => ['print_background' => true, 'scale' => 1.0, 'display_header_footer' => true],
            ],
        );

        $this->syncVariables($template, TravelDocumentCatalog::flightVariables());
    }

    private function seedInvoice(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Invoice</title>
    <style>
        @font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-regular') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-bold') format('woff2'); font-weight: 700; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: Arial, 'Vazirmatn', sans-serif; direction: ltr; margin: 0; color: #111827; font-size: 12px; line-height: 1.6; }
        .page { padding: 6mm; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .brand { font-size: 24px; font-weight: 700; color: #1d4ed8; letter-spacing: 1px; }
        .brand small { display: block; font-size: 10px; font-weight: 400; color: #6b7280; letter-spacing: 0; }
        h1 { font-size: 28px; margin: 0; color: #1d4ed8; letter-spacing: 2px; }
        .meta { text-align: right; font-size: 11px; color: #374151; }
        .parties { display: flex; gap: 20px; margin-bottom: 20px; }
        .party { flex: 1; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .party h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items th { background: #eff6ff; color: #1e3a8a; text-align: left; padding: 9px 10px; font-size: 11px; text-transform: uppercase; }
        table.items td { padding: 9px 10px; border-bottom: 1px solid #e5e7eb; }
        table.items td.num, table.items th.num { text-align: right; }
        .totals { width: 45%; margin-left: auto; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 10px; }
        .totals tr.grand td { border-top: 2px solid #1d4ed8; font-weight: 700; font-size: 13px; color: #1e3a8a; }
        .notes { margin-top: 20px; background: #f9fafb; border-radius: 8px; padding: 12px 14px; font-size: 11px; color: #374151; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #9ca3af; }
        .badge { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 10px; background: #dcfce7; color: #166534; }
    </style>
</head>
<body>
    <div class="page">
        <div class="top">
            <div>
                <div class="brand">AS7TH<small>Document Generation Service</small></div>
            </div>
            <div>
                <h1>INVOICE</h1>
                <div class="meta">
                    <div><strong>No:</strong> {{ invoice_number }}</div>
                    <div><strong>Issued:</strong> @date($issue_date, 'Y-m-d')</div>
                    <div><strong>Due:</strong> @date($due_date, 'Y-m-d')</div>
                    <div><span class="badge">{{ status ?? 'UNPAID' }}</span></div>
                </div>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h3>Billed to</h3>
                <strong>{{ customer_name }}</strong>
                <div style="white-space: pre-line; color: #4b5563;">{{ customer_address ?? '' }}</div>
                <div>{{ customer_email ?? '' }}</div>
            </div>
            <div class="party">
                <h3>From</h3>
                <strong>{{ company_name ?? 'AS7TH Travel Group' }}</strong>
                <div>{{ company_address ?? 'Tehran, Iran' }}</div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th>Description</th>
                    <th class="num" style="width: 12%;">Qty</th>
                    <th class="num" style="width: 18%;">Unit price</th>
                    <th class="num" style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ loop.iteration }}</td>
                    <td>{{ item.description }}</td>
                    <td class="num">{{ item.quantity }}</td>
                    <td class="num">{{ item.unit_price | number }}</td>
                    <td class="num">{{ item.amount | number }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td class="num">{{ subtotal | number }} {{ currency }}</td>
                </tr>
                @if(($discount ?? 0) > 0)
                <tr>
                    <td>Discount</td>
                    <td class="num">-{{ discount | number }} {{ currency }}</td>
                </tr>
                @endif
                <tr>
                    <td>Tax ({{ tax_rate ?? 0 }}%)</td>
                    <td class="num">{{ tax | number }} {{ currency }}</td>
                </tr>
                <tr class="grand">
                    <td>Total due</td>
                    <td class="num">{{ total | number }} {{ currency }}</td>
                </tr>
            </table>
        </div>

        @if(isset($notes) && $notes)
        <div class="notes">
            <strong>Notes:</strong> {{ notes }}
        </div>
        @endif
    </div>
</body>
</html>
HTML;

        $template = Template::query()->updateOrCreate(
            ['slug' => 'invoice-standard'],
            [
                'name' => 'فاکتور استاندارد (Invoice)',
                'category' => 'finance',
                'description' => 'فاکتور فروش انگلیسی با آیتم‌های داینامیک، تخفیف و مالیات.',
                'html' => $html,
                'is_active' => true,
                'locale' => 'en',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 12, 'right' => 12, 'bottom' => 16, 'left' => 12],
                'pdf_options' => ['print_background' => true, 'scale' => 1.0],
            ],
        );

        $variables = [
            ['name' => 'invoice_number', 'label' => 'Invoice number', 'type' => 'string', 'is_required' => true, 'sort_order' => 1],
            ['name' => 'issue_date', 'label' => 'Issue date', 'type' => 'date', 'is_required' => true, 'sort_order' => 2],
            ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date', 'is_required' => true, 'sort_order' => 3],
            ['name' => 'customer_name', 'label' => 'Customer name', 'type' => 'string', 'is_required' => true, 'sort_order' => 4],
            ['name' => 'customer_address', 'label' => 'Customer address', 'type' => 'text', 'is_required' => false, 'sort_order' => 5],
            ['name' => 'customer_email', 'label' => 'Customer email', 'type' => 'string', 'is_required' => false, 'validation_rules' => 'email', 'sort_order' => 6],
            ['name' => 'company_name', 'label' => 'Company name', 'type' => 'string', 'is_required' => false, 'sort_order' => 7],
            ['name' => 'items', 'label' => 'Line items', 'type' => 'array', 'is_required' => true, 'description' => 'Array of {description, quantity, unit_price, amount}', 'sort_order' => 8],
            ['name' => 'subtotal', 'label' => 'Subtotal', 'type' => 'decimal', 'is_required' => true, 'min_value' => 0, 'sort_order' => 9],
            ['name' => 'discount', 'label' => 'Discount', 'type' => 'decimal', 'is_required' => false, 'default_value' => '0', 'min_value' => 0, 'sort_order' => 10],
            ['name' => 'tax_rate', 'label' => 'Tax rate (%)', 'type' => 'decimal', 'is_required' => false, 'default_value' => '0', 'sort_order' => 11],
            ['name' => 'tax', 'label' => 'Tax amount', 'type' => 'decimal', 'is_required' => true, 'min_value' => 0, 'sort_order' => 12],
            ['name' => 'total', 'label' => 'Total due', 'type' => 'decimal', 'is_required' => true, 'min_value' => 0, 'sort_order' => 13],
            ['name' => 'currency', 'label' => 'Currency', 'type' => 'string', 'is_required' => true, 'default_value' => 'USD', 'validation_rules' => 'max:10', 'sort_order' => 14],
            ['name' => 'status', 'label' => 'Status', 'type' => 'string', 'is_required' => false, 'default_value' => 'UNPAID', 'sort_order' => 15],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'is_required' => false, 'sort_order' => 16],
        ];

        $this->syncVariables($template, $variables);
    }

    private function seedTourItinerary(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>برنامه سفر</title>
    <style>
        @font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-regular') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-bold') format('woff2'); font-weight: 700; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; direction: rtl; margin: 0; color: #1f2937; font-size: 12px; line-height: 1.9; }
        .page { padding: 6mm; }
        .cover { background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%); color: #fff; border-radius: 14px; padding: 30px 26px; margin-bottom: 22px; }
        .cover h1 { margin: 0 0 8px; font-size: 26px; }
        .cover p { margin: 0; opacity: .9; }
        .meta { display: flex; gap: 12px; margin-bottom: 20px; }
        .meta .box { flex: 1; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; text-align: center; }
        .meta .box span { display: block; font-size: 10px; color: #64748b; }
        .meta .box strong { font-size: 14px; }
        .day { border-right: 4px solid #0f766e; background: #f8fafc; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; page-break-inside: avoid; }
        .day-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .day-title { font-weight: 700; color: #0f766e; font-size: 14px; }
        .day-number { background: #0f766e; color: #fff; border-radius: 999px; padding: 2px 10px; font-size: 11px; }
        .day p { margin: 4px 0; }
        .day .meta-line { color: #64748b; font-size: 11px; }
        .two-col { display: flex; gap: 16px; margin-top: 18px; }
        .two-col > div { flex: 1; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; }
        .two-col h3 { margin: 0 0 8px; font-size: 13px; color: #0f766e; }
        ul { margin: 0; padding-right: 18px; }
        .terms { margin-top: 18px; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px; padding: 10px 14px; font-size: 11px; }
        .page-break { page-break-after: always; }
        .contact { margin-top: 20px; text-align: center; font-size: 11px; color: #475569; }
    </style>
</head>
<body>
    <div class="page">
        <div class="cover">
            <h1>{{ tour_name }}</h1>
            <p>کد تور: {{ tour_code }} | از @jdate($start_date, 'j F Y') تا @jdate($end_date, 'j F Y')</p>
            <p>تعداد مسافران: {{ traveler_count }} نفر</p>
        </div>

        <div class="meta">
            <div class="box"><span>مدت سفر</span><strong>{{ duration_days ?? 0 }} روز</strong></div>
            <div class="box"><span>مقصد</span><strong>{{ destination ?? '—' }}</strong></div>
            <div class="box"><span>شماره تماس اضطراری</span><strong>{{ emergency_contact ?? '—' }}</strong></div>
        </div>

        @foreach($days as $day)
        <div class="day">
            <div class="day-head">
                <span class="day-title">{{ day.title }}</span>
                <span class="day-number">روز {{ day.day_number }}</span>
            </div>
            <p>{{ day.description }}</p>
            @if(isset($day.meals) && $day.meals)
            <p class="meta-line">وعده‌ها: {{ day.meals }}</p>
            @endif
            @if(isset($day.hotel) && $day.hotel)
            <p class="meta-line">اقامتگاه: {{ day.hotel }}</p>
            @endif
        </div>
        @endforeach

        <div class="two-col">
            <div>
                <h3>خدمات شامل تور</h3>
                <ul>
                    @foreach($includes ?? [] as $item)
                    <li>{{ item }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3>خدمات شامل نمی‌شود</h3>
                <ul>
                    @foreach($excludes ?? [] as $item)
                    <li>{{ item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        @if(isset($terms) && $terms)
        <div class="terms">
            <strong>شرایط و قوانین:</strong> {{ terms }}
        </div>
        @endif

        <div class="contact">
            این سند توسط سرویس مرکزی تولید اسناد صادر شده است. | pdf.as7th.com
        </div>
    </div>
</body>
</html>
HTML;

        $footer = <<<'HTML'
<div style="width: 100%; font-family: 'Vazirmatn', Tahoma, sans-serif; font-size: 9px; color: #64748b; direction: rtl; padding: 0 12mm;">
    <div style="display: flex; justify-content: space-between;">
        <span>برنامه سفر - نسخه چاپی</span>
        <span>صفحه <span class="pageNumber"></span> از <span class="totalPages"></span></span>
    </div>
</div>
HTML;

        $template = Template::query()->updateOrCreate(
            ['slug' => 'tour-itinerary'],
            [
                'name' => 'برنامه سفر گروهی (Tour Itinerary)',
                'category' => 'tour',
                'description' => 'برنامه سفر چندصفحه‌ای با روزشمار، خدمات شامل/غیرشامل و شماره صفحه.',
                'html' => $html,
                'footer_html' => $footer,
                'is_active' => true,
                'locale' => 'fa',
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'right' => 10, 'bottom' => 18, 'left' => 10],
                'pdf_options' => ['print_background' => true, 'scale' => 1.0, 'display_header_footer' => true],
            ],
        );

        $variables = [
            ['name' => 'tour_name', 'label' => 'نام تور', 'type' => 'string', 'is_required' => true, 'sort_order' => 1],
            ['name' => 'tour_code', 'label' => 'کد تور', 'type' => 'string', 'is_required' => true, 'sort_order' => 2],
            ['name' => 'start_date', 'label' => 'تاریخ شروع', 'type' => 'date', 'is_required' => true, 'sort_order' => 3],
            ['name' => 'end_date', 'label' => 'تاریخ پایان', 'type' => 'date', 'is_required' => true, 'sort_order' => 4],
            ['name' => 'traveler_count', 'label' => 'تعداد مسافران', 'type' => 'integer', 'is_required' => true, 'min_value' => 1, 'sort_order' => 5],
            ['name' => 'duration_days', 'label' => 'مدت سفر (روز)', 'type' => 'integer', 'is_required' => false, 'sort_order' => 6],
            ['name' => 'destination', 'label' => 'مقصد', 'type' => 'string', 'is_required' => false, 'sort_order' => 7],
            ['name' => 'days', 'label' => 'روزهای سفر', 'type' => 'array', 'is_required' => true, 'description' => 'Array of {day_number, title, description, meals, hotel}', 'sort_order' => 8],
            ['name' => 'includes', 'label' => 'خدمات شامل', 'type' => 'array', 'is_required' => false, 'sort_order' => 9],
            ['name' => 'excludes', 'label' => 'خدمات غیرشامل', 'type' => 'array', 'is_required' => false, 'sort_order' => 10],
            ['name' => 'emergency_contact', 'label' => 'تماس اضطراری', 'type' => 'string', 'is_required' => false, 'sort_order' => 11],
            ['name' => 'terms', 'label' => 'شرایط و قوانین', 'type' => 'text', 'is_required' => false, 'sort_order' => 12],
        ];

        $this->syncVariables($template, $variables);
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     */
    private function syncVariables(Template $template, array $variables): void
    {
        $names = array_column($variables, 'name');
        $template->variables()->whereNotIn('name', $names)->delete();

        foreach ($variables as $variable) {
            $template->variables()->updateOrCreate(
                ['name' => $variable['name']],
                array_merge([
                    'label' => $variable['name'],
                    'type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => null,
                    'validation_rules' => null,
                    'sort_order' => 0,
                ], $variable),
            );
        }

        $template->load('variables');
        $template->forceFill(['version' => 1])->save();
        $template->versions()->delete();
        $template->snapshotVersion(null, 'نسخه اولیه (Seeder)', false);
    }
}
