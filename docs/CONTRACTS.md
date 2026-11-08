# قراردادهای پیاده‌سازی — Central PDF Service

این سند مرجع مشترک تیم است. هر توسعه‌دهنده فقط محدودهٔ فایل‌های خود را تغییر می‌دهد و
قراردادهای این سند را رعایت می‌کند. تغییر در فایل‌های مشترک (migration/model/config/routes
مالکیتی) فقط از طریق Lead انجام می‌شود.

## 1) محیط و دستورات

- پروژه: `E:\projects\pdf_creator_api` (Laravel 13، PHP 8.5)
- اجرای Artisan/تست با PHP 8.5:

```powershell
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"
& $php artisan migrate --force
& $php artisan test --filter=Something
& $php vendor\bin\phpunit tests/Feature/Api
```

- دیتابیس dev: MySQL `pdf_service` (اطلاعات اتصال فقط در `.env` محلی؛ هیچ رمزی در این سند/مخزن نیست). تست‌ها: SQLite حافظه‌ای طبق `phpunit.xml`.
- Gotenberg در این ماشین در دسترس نیست؛ همهٔ تست‌ها با `Http::fake()` نوشته می‌شوند.
- فونت‌های Vazirmatn در `resources/fonts/` و asset رکوردها با handleهای
  `vazirmatn-regular` و `vazirmatn-bold` seed شده‌اند.
- npm فقط توسط مسئول پنل اجرا می‌شود. هیچ‌کس git اجرا نکند (Lead انجام می‌دهد).
- هیچ فایلی از پروژه‌های دیگر (`pdf_maker`, `Mylaravel13`, ...) خوانده یا تغییر داده نشود.

## 2) مالکیت فایل‌ها (Write Scope)

| محدوده | مالک |
|---|---|
| `database/migrations/`, `app/Models/`, `app/Enums/`, `database/factories/`, `database/seeders/`, `app/Policies/`, `config/`, `bootstrap/app.php`, `app/Providers/`, `routes/console.php`, `app/Support/`, `app/Http/Middleware/EnsureUserIsActive.php`, `app/Services/Settings/`, `app/Services/Api/ApiKeyService.php` | Lead |
| `app/Services/Templates/**`, `app/Services/Gotenberg/**`, `app/Services/Documents/**`, `app/Services/Security/**`, `app/Jobs/`, `app/Events/`, `app/Console/Commands/` (engine-related), `tests/Unit/Templates/**`, `tests/Unit/Gotenberg/**`, `tests/Feature/Documents/**` | pdf-engine |
| `app/Http/Controllers/Api/**`, `app/Http/Requests/Api/**`, `app/Http/Resources/**`, `app/Http/Middleware/` (به‌جز EnsureUserIsActive), `app/Services/Api/` (به‌جز ApiKeyService.php)، `app/Exceptions/`, `routes/api.php`, `resources/views/api/**`, `docs/openapi.yaml`, `tests/Feature/Api/**` | api-dev |
| `app/Http/Controllers/Admin/**`, `app/Http/Controllers/Auth/**`, `app/Http/Requests/Admin/**`, `app/Services/Assets/**`, `routes/web.php`, `resources/views/**` (به‌جز `views/api`), `resources/css/**`, `resources/js/**`, `vite.config.js`, `package.json`, `lang/fa/**`, `tests/Feature/Admin/**` | panel-dev |
| `README.md`, `docs/*.md` (به‌جز `docs/CONTRACTS.md` و `docs/openapi.yaml`), `deploy/**`, `scripts/**` | docs-dev |

اگر به تغییر در محدودهٔ دیگری نیاز بود، به Lead پیام بدهید؛ خودتان فایل مالکیت‌شدهٔ دیگری را تغییر ندهید.

## 3) مدل داده (خلاصه)

- `users`: `role` (admin/manager/viewer enum)، `is_active`، `last_login_at`.
- `api_projects`: `name`, `code` (unique), `description`, `is_active`, `rate_limit_per_minute`, `monthly_quota`, `last_activity_at`.
- `api_keys`: `api_project_id`, `name`, `key_prefix`, `key_hash` (HMAC-SHA256), `is_active`, `expires_at`, `rate_limit_per_minute`, `last_used_at`, `revoked_at`, soft delete.
- `templates`: `name`, `slug` unique, `category`, `description`, `html`, `css`, `header_html`, `footer_html`, `is_active`, `locale`, `version`, `page_size` (A4/A3/A5/Letter/Legal/Custom), `orientation`، `margins` json، `pdf_options` json، soft delete. متدهای مهم: `variables()`, `variablesSchema()`, `pdfOptionsArray()`, `marginsArray()`, `snapshotVersion()`, `createNewVersion()`, `revertToVersion()`, `copyAs()`.
- `template_variables`: `name` (identifier), `label`, `type` (string|text|integer|decimal|boolean|date|datetime|image|url|array|object), `is_required`, `default_value`, `description`, `validation_rules`, `min_length`, `max_length`, `min_value`, `max_value`, `sort_order`. متد `validationRules()` و `schemaEntry()`.
- `template_versions`: snapshot کامل + `variables` json + `note` + `created_by`.
- `api_project_template`: دسترسی پروژه به قالب‌ها (خالی = دسترسی به همهٔ قالب‌های فعال).
- `generated_documents`: `ulid` (شناسه عمومی)، `reference`, project/key/template ids + snapshot `template_name`/`template_version`, `generation_mode` (sync/async), `status` (pending/processing/completed/failed), `file_path`, `file_size`, `mime_type`, `pdf_meta`, `payload` (encrypted array), `options`, `error_code`, `error_message`, `expires_at`, `generated_at`, soft delete. متدهای `markProcessing()`, `markCompleted()`, `markFailed()`, `canDownload()`.
- `document_jobs`: ردیف ردیابی صف: `status`, `attempts`, `max_attempts`, timestamps, `duration_ms`, `error_code`, `error_message`. متدهای `markProcessing()`, `markCompleted()`, `markFailed()`, `canRetry()`.
- `api_request_logs`: `request_id`, project/key/template/document ids, method/path/status/duration, sizes, ip, user_agent, error_code/message. هرگز بدنه یا هدر Authorization ذخیره نشود.
- `assets`: `handle` unique (ارجاع `asset://handle`), type (font/image/css/other), mime, size, disk, path, checksum.
- `settings`: key/value + type + group؛ دسترسی با `SettingsService`.
- `idempotency_keys`: `(api_project_id, key)` unique + `request_hash`, `generated_document_id`, `response_status`, `expires_at`.

## 4) موتور قالب امن (pdf-engine)

هدف: رندر قالب‌های ذخیره‌شده در دیتابیس **بدون اجرای PHP** و بدون دسترسی به فایل‌سیستم/شبکهٔ
دلخواه. کلاس‌های پیشنهادی:

- `App\Services\Templates\SafeTemplateRenderer`
  - `render(string $html, array $data, string $locale = 'fa'): string`
  - سینتکس پشتیبانی‌شده (Blade-like):
    - `{{ expression }}` — خروجی HTML-escaped؛ فیلترها: `| number`, `| jdate`, `| jdatetime`, `| upper`, `| lower`, `| trim`, `| nl2br`, `| default('x')`, `| truncate(80)`, `| json`
    - `@if(expr) ... @elseif(expr) ... @else ... @endif`
    - `@unless(expr) ... @endunless`
    - `@foreach($items as $item)` و `@foreach($items as $key => $item)` ... `@endforeach`؛ متغیر `loop` با `loop.index` (۰-based)، `loop.iteration` (۱-based)، `loop.first`، `loop.last`، `loop.count`، `loop.remaining`
    - `@isset($x) ... @endisset`، `@empty($x) ... @endempty`
    - `@jdate(expr)` / `@jdate(expr, 'j F Y')` — خروجی تاریخ شمسی (escaped)
    - `@date(expr)` / `@date(expr, 'Y-m-d')` — خروجی تاریخ میلادی
    - `@number(expr)` — عدد با جداکننده هزارگان
    - کامنت `{{-- ... --}}`
  - عبارت‌ها: متغیر با `$` یا بدون آن، دسترسی نقطه‌ای (`guest.name`) و ایندکس (`items[0]`)، null coalescing `??`، عملگرهای مقایسه/منطقی/ریاضی، پرانتز، رشته/عدد/bool/null. فراخوانی تابع فقط برای allowlist: `count`, `now`, `strlen`, `mb_strlen`, `round`, `abs`, `max`, `min`, `intval`, `floatval`. هیچ متد/کلاس/`$GLOBALS`/بک‌تیک/`eval` مجاز نیست.
  - خطاهای سینتکسی باید `App\Exceptions\TemplateRenderException` (یا مشابه) با پیام فارسی قابل‌نمایش پرتاب کنند؛ هرگز متن PHP خام به خروجی نشت نکند.
  - قالب نامعتبر/غیرمجاز (وجود `<?php`, `@php`, `@endphp`, `<?`, `{!!`, `@include`, `@extends`, `@inject`, `@use`) باید قبل از ذخیره و قبل از رندر رد شود؛ یک `TemplateValidator`/`TemplateSanitizer` برای این کار بسازید.
- `App\Services\Templates\TemplateSanitizer` — حذف `<script>`, `<iframe>`, `<object>`, `<embed>`، attributeهای `on*`، `javascript:`، importهای خارجی CSS و URLهای خارجی (به‌جز `asset://`, `data:`, `#`). `script` هیچ‌گاه اجرا نمی‌شود.
- `App\Services\Templates\TemplateSchemaValidator`
  - `validate(Template $template, array $data): array` — اعمال defaultها، تبدیل نوع، اعتبارسنجی طبق `validationRules()`؛ در خطا `Illuminate\Validation\ValidationException` با کلیدهای `data.<name>` پرتاب می‌شود تا API آن را به 422 استاندارد تبدیل کند.
  - `rules(Template $template): array`
- `App\Services\Templates\TemplateRenderService` (یا `DocumentRenderer`)
  - `render(Template $template, array $data): RenderedDocument` — HTML نهایی + هدر/فوتر + attachmentها + صفحه‌بندی.
  - `RenderedDocument` DTO: `html` (فایل index.html)، `headerHtml`، `footerHtml`، `assets` (map filename => contents، همه flattened در ریشه: `{handle}.{ext}`)، `filename`، `pdfOptions`.
  - جایگزینی `asset://handle` در HTML/CSS/هدر/فوتر با نام فایل asset و بارگذاری محتوا از `Asset` (disk/path). handle ناموجود → خطای کنترل‌شده.
  - متغیرهای نوع `image`: مقدار می‌تواند `asset://handle`، `data:image/...;base64,...` یا URL عمومی باشد. URL با `App\Services\Security\ExternalUrlGuard` (فقط http/https، resolve DNS و رد private/reserved IP، محدودیت حجم/نوع، timeout) دانلود و به data URI تبدیل شود. برای تست‌ها HTTP fake می‌شود.
- `App\Services\Gotenberg\GotenbergService`
  - `generate(RenderedDocument $document, PdfOptions $options): GotenbergResult` — multipart به `POST {url}/forms/chromium/convert/html`؛ فیلدها: `files` شامل `index.html` و assetها (و `header.html`/`footer.html`)، `paperWidth`/`paperHeight` (اینچ) یا `format`، `marginTop/Right/Bottom/Left` (اینچ)، `printBackground`، `scale`، `landscape`، `preferCssPageSize`، `displayHeaderFooter`.
  - timeout/connect_timeout از config + retry محدود؛ خطاها به `GotenbergException` با کد `GOTENBERG_UNAVAILABLE` / `GOTENBERG_TIMEOUT` / `GOTENBERG_ERROR`؛ هرگز محتوای HTML یا داده‌های حساس در لاگ ننویسید.
  - `health(): array` — `GET /health` با timeout کوتاه، بدون exception بیرون‌زننده.
- `App\Services\Documents\DocumentGenerationService`
  - `generateSync(Template $template, array $data, array $options = [], ?ApiProject $project = null, ?ApiKey $apiKey = null, ?string $reference = null, ?User $user = null): GeneratedDocument`
  - `queue(Template $template, array $data, array $options = [], ?ApiProject $project = null, ?ApiKey $apiKey = null, ?string $reference = null): GeneratedDocument` — سند `pending` + DocumentJob + `GenerateDocumentJob::dispatch`.
  - `retry(GeneratedDocument $document): void` — فقط برای failed؛ job جدید/به‌روزرسانی.
  - `preview(Template $template, array $data, array $options = []): array{contents: string, duration_ms: int, size: int}` — بدون persist (برای پنل).
  - `renderHtml(Template $template, array $data): RenderedDocument` (برای پیش‌نمایش HTML).
  - `generateSync` باید: render → gotenberg → store (DocumentStorageService) → mark completed + pdf_meta؛ خطا → mark failed و exception با کد.
- `App\Services\Documents\DocumentStorageService`
  - `store(GeneratedDocument $document, string $contents): string` مسیر نسبی در disk خصوصی
  - `read`, `exists`, `delete`, `absolutePath`
  - مسیر: `{pdf.document_path}/{YYYY}/{MM}/{ulid}.pdf`؛ هرگز در public.
- `App\Jobs\GenerateDocumentJob` — `tries`/`timeout`/`backoff` از config؛ document_jobs را pending→processing→completed/failed می‌کند؛ exception را log و job را failed می‌کند؛ از تکرار هم‌زمان جلوگیری شود (`ShouldBeUnique` با `uniqueId` = document id).
- `App\Events\DocumentGenerated` و `DocumentGenerationFailed` (اختیاری listener ندارد).
- خطاها با `error_code` ثابت: `GOTENBERG_UNAVAILABLE`, `GOTENBERG_TIMEOUT`, `GOTENBERG_ERROR`, `RENDER_ERROR`, `STORAGE_ERROR`, `VALIDATION_ERROR`, `TEMPLATE_NOT_FOUND`, `TEMPLATE_INACTIVE`.

## 5) API v1 (api-dev)

پیشوند `/api/v1`. احراز هویت Bearer با `ApiKeyService::authenticate`. ساختار خطا:

```json
{"success": false, "error": {"code": "INVALID_API_KEY", "message": "...", "details": {}}}
```

پاسخ موفق JSON: `{"success": true, "data": {...}, "meta": {...}}`.

| متد | مسیر | توضیح |
|---|---|---|
| POST | `/documents/generate` | تولید هم‌زمان؛ خروجی فایل PDF (`application/pdf`) با `Content-Disposition: attachment`. هدرها: `X-Document-Id` (ulid)، `X-Generation-Time-Ms`، `X-Request-Id`. |
| POST | `/documents/queue` | تولید غیرهم‌زمان → `202` با `{id, status, status_url, download_url}`. |
| GET | `/documents/{ulid}/status` | وضعیت سند. |
| GET | `/documents/{ulid}/download` | دانلود با Bearer (فقط completed/غیرمنقضی). |
| GET | `/documents/{ulid}/download/signed` | دانلود با temporary signed URL (بدون Bearer، امضا اجباری) — برای `download_url` در status. |
| GET | `/templates` | لیست قالب‌های مجاز پروژه + schema متغیرها. |
| GET | `/templates/{slug}` | جزئیات قالب مجاز. |
| GET | `/health` | عمومی؛ وضعیت سرویس/DB/Gotenberg (بدون جزئیات حساس). |
| GET | `/docs` | صفحه Swagger UI. |
| GET | `/openapi.yaml` | فایل OpenAPI. |

- بدنهٔ `generate`/`queue`: `{"template": "slug", "reference": "...", "data": {...}, "options": {"format": "A4", "orientation": "portrait"}}`. `data` باید object باشد. حداکثر حجم: `pdf.max_payload_kb`.
- کنترل دسترسی: `ApiProject::canUseTemplate($template)`؛ قالب inactive → `TEMPLATE_INACTIVE` (422/403). پروژه/کلید inactive → 401/403.
- Rate limit: per API key (override کلید یا پروژه یا config)، پاسخ 429 با `Retry-After` و هدرهای `X-RateLimit-Limit/Remaining/Reset`. سقف ماهانه پروژه نیز بررسی شود (`MONTHLY_QUOTA_EXCEEDED`).
- Idempotency: هدر اختیاری `Idempotency-Key`؛ TTL از config؛ دوباره‌ارسال با همان بدنه همان سند/فایل را برمی‌گرداند؛ بدنه متفاوت با همان کلید → `IDEMPOTENCY_KEY_REUSED` (422).
- Middlewareها: احراز هویت، rate limit، ثبت لاگ، محدودیت payload، ForceJson. `request_id` تولید و در پاسخ `X-Request-Id` برگردد.
- هرگز خطای داخلی/stack trace به مصرف‌کننده نشان داده نشود (prod پیام عمومی، کد داخلی log شود).
- Swagger UI: صفحهٔ `resources/views/api/docs.blade.php` با CDN (یا asset) و لینک به `/api/v1/openapi.yaml`.
- تست‌ها: auth (بدون/نامعتبر/منقضی/revoked)، عدم دسترسی به قالب غیرمجاز، validation متغیرها (422)، generate (Http::fake → 200 PDF و ذخیره فایل)، Persian/multipage mock (assert body ارسال‌شده)، queue/status/download، signed download، rate limit (429)، idempotency، payload large (413)، health. برای Gotenberg از `Http::fake` و برای storage از `Storage::fake` استفاده شود.

## 6) پنل مدیریت (panel-dev)

همه مسیرها زیر `/admin`، نام‌گذاری `admin.*`، زبان فارسی، RTL، Bootstrap 5.3، فونت Vazirmatn، تاریخ شمسی با `jdate()`/`<x-jdate>`.

- ورود/خروج با session (ایمیل/رمز)، throttle، پیام فارسی، جلوگیری از ورود کاربر inactive؛ ریدایرکت مهمان به `/admin/login`.
- داشبورد: کل اسناد، اسناد امروز، درخواست‌های API، موفق/ناموفق، پروژه‌های فعال، قالب‌های فعال، میانگین زمان تولید (از `document_jobs.duration_ms` completed).
- مدیریت قالب‌ها: index (جستجو/فیلتر دسته/وضعیت)، create/edit (HTML/CSS/Header/Footer + تنظیمات صفحه + متغیرها)، copy، activate/deactivate، delete، preview (JSON داده نمونه → پیش‌نمایش HTML/PDF)، نسخه‌ها (لیست/مشاهده/بازگردانی)، نمایش لیست متغیرها با دکمه درج در ادیتور.
- پروژه‌ها و کلیدها: CRUD پروژه، اتصال قالب‌های مجاز، سقف نرخ/ماهانه، ساخت کلید (نمایش یک‌بارهٔ کلید با کپی، ذخیره در session flash و عدم نمایش مجدد)، چرخش/لغو/انقضا، آخرین استفاده، مصرف.
- اسناد: index با جستجو (reference/ulid/نام قالب) و فیلترها (وضعیت، پروژه، قالب، بازه تاریخ شمسی)، جزئیات، دانلود، حذف، retry ناموفق.
- صف: لیست DocumentJobها + failed_jobs، فیلتر وضعیت، retry/delete، نمایش attempts/duration/error.
- لاگ‌ها: API request logs با فیلتر (پروژه، وضعیت، بازه، جستجو) + نمایش tail فایل laravel.log با فیلتر سطح (محدود، فقط admin).
- فایل‌ها/فونت‌ها: آپلود با اعتبارسنجی mime/پسوند/حجم (config + settings)، لیست/فیلتر/پیش‌نمایش/دانلود/حذف، کپی handle، راهنمای استفاده `asset://handle`.
- تنظیمات: فرم گروه‌بندی‌شده (general/documents/api/gotenberg/security/queue) + دکمه تست اتصال Gotenberg؛ فقط admin.
- کاربران: CRUD، نقش، فعال/غیرفعال، تغییر رمز توسط admin، محافظت از خود (عدم حذف/غیرفعال‌سازی خود) + مدیر آخر.
- UI: sidebar/topbar responsive، flash alerts (success/error)، breadcrumb، pagination Bootstrap 5، empty state، confirm برای حذف، CSRF همه فرم‌ها، XSS escape، پیام‌های validation فارسی (`lang/fa`).
- تست‌ها: مهمان → redirect login، ورود موفق/ناموفق، دسترسی viewer/manager/admin، CRUD قالب + متغیرها + نسخه، مدیریت پروژه/کلید، فیلتر اسناد، آپلود asset نامعتبر رد شود، تنظیمات فقط admin، داشبورد 200.

## 7) مستندات و استقرار (docs-dev)

- `README.md`: معرفی، امکانات، نصب لوکال، متغیرهای env، دستورات، ساختار، تست.
- `docs/ARCHITECTURE.md`: معماری، جریان تولید سند، امنیت قالب، مدل داده، تصمیمات.
- `docs/DEPLOYMENT.md`: نصب روی Ubuntu 22.04 / PHP 8.5 / MySQL 8 / Nginx / Gotenberg 9 / Redis؛ ساخت دیتابیس، `.env`، مجوزها، `artisan migrate --seed`، `npm run build`، queue worker (systemd)، scheduler (cron)، Nginx vhost `pdf.as7th.com`، SSL، health، rollback، backup.
- `docs/SECURITY.md`: تهدیدها و کنترل‌ها (XSS/SSRF/path traversal/آپلود/قالب/PHP/API key/rate limit/لاگ امن).
- `docs/OPERATIONS.md`: مانیتورینگ، لاگ‌ها، پاک‌سازی اسناد منقضی، retry صف، بازیابی.
- `deploy/nginx/pdf.as7th.com.conf` (فقط سایت جدید؛ سایر سایت‌ها دست نخورند)، `deploy/systemd/pdf-queue.service`, `deploy/systemd/pdf-scheduler.service` یا cron، `deploy/deploy.sh`، `deploy/.env.production.example`.
- `scripts/integration-test.ps1` یا `.sh`: تست واقعی Gotenberg/تولید PDF فارسی/چندصفحه‌ای علیه سرور محلی یا remote (بدون hardcode secret).
- هیچ credentialed در git نباشد.

## 8) تست و کنترل کیفیت

- PHPUnit 12؛ `RefreshDatabase`.
- Gotenberg همیشه mock شود (`Http::fake`)؛ Storage در تست‌ها `Storage::fake('local')`.
- تست‌های واقعی Gotenberg فقط از طریق اسکریپت integration جداگانه.
- پس از تغییر، فقط تست‌های خودتان را اجرا کنید؛ Lead در پایان کل suite را اجرا می‌کند.
- کد: `declare(strict_types=1)`، PSR-12، بدون `dd/dump`، بدون کلید/رمز hardcode، پیام‌های کاربر فارسی.

## 9) تحویل هر teammate

1. فایل‌های ایجاد/تغییر یافته را در پیام نهایی لیست کند.
2. تست‌های نوشته‌شده و نتیجهٔ اجرای آن‌ها را گزارش کند.
3. هر چیزی که ناقص/شکسته ماند را صریح بگوید.
4. اگر به تغییر در فایل خارج از محدودهٔ خود نیاز دارد، به Lead پیام دهد و منتظر بماند.
