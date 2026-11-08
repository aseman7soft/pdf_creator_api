<div dir="rtl">

# راهنمای تست (Testing)

این سند نحوهٔ اجرای تست‌های خودکار (PHPUnit) و تست یکپارچهٔ واقعی (Gotenberg) را توضیح می‌دهد. مرجع قرارداد تست‌ها: [CONTRACTS.md](CONTRACTS.md) بخش ۸.

## ۱) پیش‌نیازها

- PHP 8.5 (حداقل 8.3) با افزونه‌های `pdo_sqlite`, `mbstring`, `xml`, `curl`, `fileinfo`
- Composer 2 (نصب `vendor/`)
- برای اسکریپت یکپارچه: `curl` و یکی از `jq/php/python3` (برای parse JSON)

در ماشین توسعهٔ ویندوز، PHP 8.5 در Herd:

```powershell
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"
& $php -v
```

## ۲) تنظیمات تست

تست‌ها طبق `phpunit.xml` روی **SQLite حافظه‌ای** اجرا می‌شوند:

- `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- `QUEUE_CONNECTION=sync` (jobها هم‌زمان اجرا می‌شوند)
- `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`
- `BCRYPT_ROUNDS=4` (سرعت بیشتر)
- `RefreshDatabase` در تست‌ها دیتابیس را برای هر تست بازسازی می‌کند

**Gotenberg هرگز در تست‌های خودکار واقعی فراخوانی نمی‌شود**؛ همهٔ تست‌ها با `Http::fake()` نوشته می‌شوند. Storage نیز در تست‌ها با `Storage::fake('local')` جایگزین می‌شود.

## ۳) اجرای تست‌ها

```powershell
# ویندوز — PHP 8.5
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"

& $php artisan test                          # کل تست‌ها
& $php artisan test --filter=SafeTemplateRenderer
& $php vendor\bin\phpunit tests/Feature/Api
& $php vendor\bin\phpunit --testsuite=Unit
```

```bash
# لینوکس / macOS
php artisan test
php artisan test --filter=DocumentGeneration
php vendor/bin/phpunit tests/Feature/Api
php vendor/bin/phpunit --testsuite=Unit
```

- قبل از اجرا در صورت تغییر `.env`/config: `php artisan config:clear`
- اسکریپت Composer: `composer test` (شامل `config:clear` و `artisan test`)
- اجرای فقط تست‌های خودتان (طبق قرارداد تیم) برای صرفه‌جویی در زمان؛ Lead در پایان کل suite را اجرا می‌کند.

### ساختار تست‌ها

```text
tests/
├── TestCase.php
├── Unit/
│   ├── Support/JalaliTest.php           # تبدیل تاریخ شمسی
│   ├── Templates/…                      # tokenizer/parser/evaluator/renderer/sanitizer/schema
│   └── Gotenberg/…                      # کلاینت Gotenberg با Http::fake
└── Feature/
    ├── Foundation/…                     # ApiKeyService، SettingsService
    ├── Api/…                            # auth, rate limit, idempotency, generate, queue, payload, logs, docs
    ├── Admin/…                          # پنل، CRUD، مجوزها، آپلود، تنظیمات
    └── Documents/…                      # سرویس تولید سند، storage، SSRF guard، job/صف، دستورات، رندر قالب‌های seed
```

## ۴) قواعد نوشتن تست (تیم)

- `RefreshDatabase` در Feature testها؛ در Unit testها در صورت نیاز به DB.
- Gotenberg: `Http::fake([...])` با پاسخ PDF ساختگی؛ assert مسیر/فیلدهای multipart و هدرها.
- Storage: `Storage::fake('local')`؛ assert وجود/عدم وجود فایل.
- احراز هویت API: ساخت `ApiProject` + `ApiKey` با `ApiKeyService::create()` و استفاده از `plain`.
- بدون `dd/dump`، بدون وابستگی تست به اینترنت، بدون کلید/رمز hardcode.
- برای داده‌های فارسی از رشته‌های UTF-8 استفاده کنید؛ تست‌های رندر را با متغیرهای واقعی (مثل `hotel-voucher`) بنویسید.

## ۵) تست یکپارچهٔ واقعی (Gotenberg)

تست‌های PHPUnit موتور واقعی را صدا نمی‌زنند؛ برای اعتبارسنجی end-to-end از اسکریپت‌های `scripts/` استفاده کنید. این تست‌ها به **سرویس در حال اجرا + Gotenberg واقعی + API Key معتبر** نیاز دارند.

### ویندوز

```powershell
$env:PDF_BASE_URL = "https://pdf-creator.test"   # یا http://127.0.0.1:8000
$env:PDF_API_KEY  = "pdfsvc_xxx"                 # کلید خام؛ در فایل ذخیره نکنید
$env:PDF_TEMPLATE = "hotel-voucher"              # اختیاری
$env:PDF_SKIP_QUEUE = "0"

powershell -ExecutionPolicy Bypass -File .\scripts\integration-test.ps1

# پارامترها
.\scripts\integration-test.ps1 -BaseUrl https://pdf.as7th.com -ApiKey $env:PDF_API_KEY -SkipQueue -OutputDir C:\temp\pdf-tests
```

### لینوکس / macOS

```bash
export PDF_BASE_URL="https://pdf.as7th.com"
export PDF_API_KEY="pdfsvc_xxx"
./scripts/integration-test.sh

# گزینه‌ها با متغیر محیطی
PDF_SKIP_QUEUE=1 ./scripts/integration-test.sh
PDF_RUN_RATE_LIMIT=1 ./scripts/integration-test.sh
```

### متغیرهای محیطی اسکریپت‌ها

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `PDF_BASE_URL` | `http://127.0.0.1:8000` | آدرس پایه سرویس |
| `PDF_API_KEY` | — (**الزامی**) | کلید خام API؛ هیچ کلیدی در فایل نیست |
| `PDF_TEMPLATE` | `hotel-voucher` | قالب تست فارسی |
| `PDF_MULTIPAGE_TEMPLATE` | `tour-itinerary` | قالب چندصفحه‌ای |
| `PDF_SKIP_QUEUE` | `0` | `1` = رد کردن تست صف/دانلود |
| `PDF_RUN_RATE_LIMIT` | `0` | `1` = تست اختیاری rate limit (مصرف سهمیه) |
| `PDF_HTTP_TIMEOUT` | `120` | timeout هر درخواست (ثانیه) — فقط نسخهٔ sh |
| `PDF_QUEUE_TIMEOUT_CHECK` | `120` | انتظار برای completed شدن صف (ثانیه) — فقط sh |
| `PDF_TEST_OUTPUT_DIR` | پوشهٔ موقت | محل ذخیرهٔ فایل‌های PDF تست |

### چه چیزهایی تست می‌شود؟

1. **سلامت:** `/up` و `/api/v1/health` (و پاسخ JSON).
2. **احراز هویت:** درخواست بدون کلید باید 401/403 باشد؛ با کلید 200.
3. **دسترسی قالب:** قالب درخواستی در `/api/v1/templates` موجود باشد.
4. **تولید هم‌زمان:** `POST /api/v1/documents/generate` → 200، هدر `%PDF`، حجم معتبر، هدرهای `X-Document-Id`/`X-Request-Id` (در صورت پیاده‌سازی).
5. **رندر فارسی:** همان تست با داده‌های فارسی/RTL (متغیرهای Persian).
6. **قالب چندصفحه‌ای:** `tour-itinerary` با ۸ روز (تشخیص heuristic تعداد صفحه).
7. **صف:** `POST /api/v1/documents/queue` → 202، polling وضعیت تا `completed`، دانلود فایل.
8. **دانلود امضاشده:** `download_url` بدون Bearer کار کند و route signed بدون امضا (403/401) رد شود.
9. **Rate limit (اختیاری):** دریافت 429 با `Retry-After`.

### خروجی و کد خروج

- خلاصهٔ `PASS/FAIL/SKIP` و زمان اجرا در انتها چاپ می‌شود.
- فایل‌های PDF تست در `PDF_TEST_OUTPUT_DIR` ذخیره می‌شوند (برای بازبینی چشمی/لاگ).
- کد خروج `0` = همهٔ تست‌های اجباری موفق؛ `1` = حداقل یک شکست؛ `2` = خطای پیکربندی (مثلاً نبود API key).

### عیب‌یابی رایج

| خطا | علت | اقدام |
|---|---|---|
| `PDF_API_KEY تنظیم نشده` | متغیر محیطی خالی | کلید را از پنل بگیرید و در env بگذارید |
| `/up → 0` یا `000` | سرویس بالا نیست/آدرس غلط | سرویس را اجرا کنید؛ `PDF_BASE_URL` را بررسی کنید |
| `401/403` روی templates | کلید نامعتبر/revoked/پروژه غیرفعال | کلید و وضعیت پروژه در `/admin` |
| `قالب ... مجاز نیست` | پروژه به قالب متصل نیست | در پنل قالب را به پروژه متصل کنید یا `PDF_TEMPLATE` را تغییر دهید |
| generate → 500 با `GOTENBERG_*` | Gotenberg در دسترس نیست/timeout | سلامت `127.0.0.1:3000/health` و لاگ worker؛ [OPERATIONS.md](OPERATIONS.md) بخش ۸ |
| صف به completed نمی‌رسد | worker اجرا نمی‌شود | `php artisan queue:work --queue=documents` یا `systemctl status pdf-queue` |
| `429` | rate limit فعال | چند دقیقه صبر کنید یا کلید با سقف بالاتر |
| signed download → 403 | `PDF_BASE_URL` با `APP_URL` سرور یکی نیست (امضا بر اساس URL مطلق است) | `PDF_BASE_URL` را دقیقاً همان `APP_URL` سرویس قرار دهید |
| status_url به دامنهٔ دیگر می‌رود | `APP_URL` سرور با آدرس تست متفاوت است | `APP_URL` را در `.env` اصلاح و config cache را پاک کنید |

## ۶) پیشنهاد CI

- مرحلهٔ ۱: `composer install` + `php artisan test` (بدون نیاز به Gotenberg/MySQL؛ SQLite حافظه‌ای).
- مرحلهٔ ۲ (اختیاری/staging): بالا آوردن سرویس + Gotenberg، ساخت API key آزمایشی، اجرای `scripts/integration-test.sh`، سپس پاک‌سازی اسناد تست.
- هرگز API key واقعی production را در CI استفاده نکنید؛ کلید مخصوص staging با سقف پایین بسازید.

</div>
