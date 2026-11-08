<div dir="rtl">

# امنیت (Security)

این سند مدل تهدید و کنترل‌های امنیتی سرویس مرکزی تولید اسناد را توضیح می‌دهد و هر کنترل را تا حد امکان به فایل/کلاس پیاده‌سازی متصل می‌کند. برای اجرای عملی سخت‌سازی به [DEPLOYMENT.md](DEPLOYMENT.md) بخش ۱۸ و برای رانبوک حادثه به [OPERATIONS.md](OPERATIONS.md) مراجعه کنید.

## ۱) دارایی‌ها و مرز اعتماد

| دارایی | محل | حساسیت |
|---|---|---|
| دادهٔ متغیرهای اسناد (احتمالاً شخصی) | `generated_documents.payload` (رمزنگاری‌شده) + فایل PDF در `storage/app/private` | بالا |
| کلیدهای API | فقط `key_hash` (HMAC) در `api_keys` | بالا |
| `APP_KEY` / `API_KEY_HASH_SALT` / رمز DB | `.env` سرور و secret manager | حیاتی |
| قالب‌ها و assetها | دیتابیس + storage خصوصی | متوسط |
| لاگ درخواست‌ها | `api_request_logs` (بدون بدنه) | متوسط |

**مرزهای اعتماد:**

1. **اینترنت → Nginx/PHP:** همهٔ ورودی‌ها غیرقابل‌اعتماد هستند (داده‌های API، هدرها، فایل آپلود، URL تصویر).
2. **PHP → Gotenberg:** فقط loopback؛ Gotenberg محتوای HTML را با Chromium رندر می‌کند، اما **PHP اجرا نمی‌کند**.
3. **PHP → دیتابیس/storage:** داده‌ها با Eloquent/Storage مدیریت می‌شوند؛ مسیر فایل‌ها هرگز از ورودی کاربر ساخته نمی‌شود.
4. **قالب‌های ذخیره‌شده در DB:** ورودی «نیمه‌قابل‌اعتماد» (مدیر/manager) اما باید بدون کد اجرایی رندر شوند.

## ۲) احراز هویت و کلید API

### ذخیره‌سازی HMAC

- کلید خام با فرمت `{API_KEY_PREFIX}_{32-byte hex}` تولید می‌شود (`ApiKeyService::generatePlainKey()`).
- فقط `key_prefix` (۲۰ کاراکتر اول) و `key_hash = HMAC-SHA256(plain, API_KEY_HASH_SALT)` ذخیره می‌شوند (`ApiKeyService::hash()`).
- `key_hash` در `$hidden` مدل `ApiKey` است و در پاسخ‌های JSON سریال نمی‌شود.
- جستجوی کلید: `key_hash` unique → زمان ثابت و بدون ذخیرهٔ کلید خام.

### اعتبارسنجی

`ApiKeyService::authenticate()` این موارد را رد می‌کند:

- کلید خالی یا بیش از ۲۰۰ کاراکتر
- کلید ناشناخته (hash یافت نشد)
- کلید revoked/inactive (`ApiKey::isRevoked()`)
- کلید منقضی (`ApiKey::isExpired()`)
- پروژهٔ غیرفعال (`ApiProject.is_active`)

middleware `AuthenticateApiKey` هدر را با regex سخت‌گیرانهٔ `Bearer` می‌خواند، کلید را به attributeهای request متصل می‌کند و در همهٔ موارد رد، پاسخ یکنواخت `401 INVALID_API_KEY` با `WWW-Authenticate: Bearer` می‌دهد (بدون تفکیک «ناموجود/منقضی/revoked» به مصرف‌کننده). در هر درخواست موفق `last_used_at` کلید و `last_activity_at` پروژه به‌روزرسانی می‌شوند.

### چرخش و لغو

- `ApiKeyService::rotate()`: کلید قبلی revoke و کلید جدید با همان محدودیت‌ها ساخته می‌شود؛ کلید خام جدید یک‌بار برگردانده می‌شود.
- `ApiKeyService::revoke()`: `is_active=false` + `revoked_at=now()`.
- کلیدها soft delete می‌شوند؛ بااین‌حال `authenticate()` فقط کلید «قابل استفاده» را می‌پذیرد.
- **`API_KEY_HASH_SALT` ثابت است**؛ تغییر آن همهٔ کلیدها را باطل می‌کند (رویهٔ حادثه در بخش ۱۴).
- `last_used_at` به‌صورت `saveQuietly()` به‌روز می‌شود (`ApiKey::touchUsage()`).

## ۳) مجوزدهی و کمترین دسترسی

- نقش‌ها (`App\Enums\UserRole`): `admin` (کامل)، `manager` (قالب/پروژه/سند/صف)، `viewer` (مشاهده/دانلود).
- سیاست‌ها در `app/Policies/*` و trait `app/Policies/Concerns/ChecksUserRoles.php`:
  - `manager()` = فعال + `canManage()`؛ `admin()` = فعال + `isAdmin()`.
  - کاربران غیرفعال: `Gate::before` در `AppServiceProvider` همهٔ بررسی‌ها را `false` می‌کند؛ middleware `EnsureUserIsActive` (alias: `active.user`) نشست را باطل و به `/admin/login` هدایت می‌کند.
  - محافظت از خود: `UserPolicy::delete()` حذف خود را ممنوع می‌کند.
- کنترل دسترسی قالب در API: `ApiProject::canUseTemplate()` — پروژهٔ دارای محدودیت فقط قالب‌های متصل را می‌بیند؛ پروژهٔ بدون محدودیت فقط قالب‌های `is_active`.

## ۴) محدودیت نرخ و سهمیه

- Rate limit در لایهٔ API با کلید (اولویت: کلید → پروژه → `API_RATE_LIMIT_PER_MINUTE`): `ApiKey::effectiveRateLimit()` و `ApiProject::effectiveRateLimit()`؛ پیاده‌سازی: `App\Http\Middleware\ApiRateLimiter`.
- الگوریتم: `RateLimiter` با پنجرهٔ ۶۰ ثانیه و کلید cache به‌ازای هر API key (`api-rate:key:{id}`).
- پاسخ 429 با `Retry-After`، `X-RateLimit-Limit`، `X-RateLimit-Remaining`، `X-RateLimit-Reset` و کد `RATE_LIMIT_EXCEEDED`؛ همین هدرها روی پاسخ‌های موفق نیز ست می‌شوند.
- سهمیهٔ ماهانه: `ApiProject::monthlyUsageCount()` بر اساس `api_request_logs`؛ در صورت عبور → 429 با کد `MONTHLY_QUOTA_EXCEEDED` و `Retry-After` تا ابتدای ماه بعد. سهمیه پیش از rate limit بررسی می‌شود.
- middleware محدودیت payload: `EnsureApiPayloadLimit` با حداکثر `PDF_MAX_PAYLOAD_KB` (پیش‌فرض 1MB)؛ بزرگ‌تر → 413 `PAYLOAD_TOO_LARGE` (بر اساس `Content-Length` و در نبود آن طول واقعی بدنه).
- محدودیت اضافی Nginx (لایهٔ لبه) برای `/api/` در [../deploy/nginx/pdf.as7th.com.conf](../deploy/nginx/pdf.as7th.com.conf) به‌صورت کامنت آمده است.

## ۵) Idempotency

- هدر اختیاری `Idempotency-Key`؛ کلید در `idempotency_keys` با unique `(api_project_id, key)`.
- `request_hash` (SHA-256 بدنهٔ درخواست) ذخیره می‌شود:
  - همان کلید + همان بدنه → همان سند/فایل قبلی برمی‌گردد (بدون تولید دوباره).
  - همان کلید + بدنهٔ متفاوت → `IDEMPOTENCY_KEY_REUSED` (422).
- TTL با `API_IDEMPOTENCY_TTL_HOURS` (پیش‌فرض 24 ساعت) و ستون `expires_at`؛ پاک‌سازی دوره‌ای انجام می‌شود.
- فایدهٔ امنیتی: جلوگیری از تولید/هزینهٔ تکراری در retry شبکه و حملهٔ «ارسال مکرر» با همان کلید؛ خود کلید idempotency **جایگزین rate limit نیست** و هر دو اعمال می‌شوند.

## ۶) CSRF و امنیت نشست (پنل)

- همهٔ فرم‌های پنل دارای `@csrf` (Bootstrap/RTL)؛ درخواست‌های تغییردهنده بدون توکن رد می‌شوند.
- session از درایور دیتابیس (`SESSION_DRIVER=database`)؛ `SESSION_SECURE_COOKIE=true` در production؛ `http_only=true`؛ `same_site=lax`.
- ورود با throttle (پیش‌فرض Laravel: 5 تلاش) و پیام فارسی؛ کاربر غیرفعال حتی با نشست معتبر خارج می‌شود.
- کوکی‌های نشست فقط روی HTTPS ارسال می‌شوند و همان‌طور که در `.env.example` آمده `SESSION_ENCRYPT` اختیاری است (توصیه: `true` در صورت افزایش حساسیت).
- پنل و API روی همان دامنه‌اند؛ برای API احراز هویت Bearer است نه session/CSRF.

## ۷) XSS (قالب‌ها و پنل)

### در قالب‌های سند

- خروجی `{{ expression }}` همیشه HTML-escaped است (`htmlspecialchars` با `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`)؛ خروجی فیلترها نیز escape می‌شوند و تنها `nl2br` خروجی «safe» علامت‌گذاری می‌شود (که خودش ورودی را escape می‌کند). خروجی خام HTML وجود ندارد.
- `TemplateSanitizer` (regex-based و دفاعی) عناصر `<script>`, `<iframe>`, `<object>`, `<embed>`, `<applet>`, `<frame>` را با محتوایشان و attributeهای `on*`، `javascript:`/`vbscript:`، `data:text/html`، `expression()`، `-moz-binding`، `behavior:`، `<base>`، `meta refresh`، لینک stylesheet خارجی، `@import` و `url()` خارجی را حذف/بی‌اثر می‌کند.
- نبودن `{!! !!}` و نبودن امکان خروجی خام، حتی اگر دادهٔ متغیر شامل `<script>` باشد، آن را به متن تبدیل می‌کند.
- اجرای JavaScript در Chromium گوتنبرگ: با توجه به حذف `<script>` و نبود منابع خارجی، سطح حمله محدود است؛ در صورت نیاز می‌توان در آینده اجرای JS را سمت Gotenberg هم محدود کرد (مسئولیت زیرساخت).

### در پنل

- Blade به‌صورت پیش‌فرض escape می‌کند؛ استفاده از `{!! !!}` فقط برای محتوای کنترل‌شده/trusted و با بازبینی.
- محتوای قالب در ویرایشگر به‌صورت textarea/text نمایش داده می‌شود، نه HTML اجراشده.

## ۸) SQL Injection

- تمام دسترسی‌ها از Eloquent/Query Builder با binding پارامتر استفاده می‌کنند (`app/Models/*`، `app/Services/*`).
- جستجوها از `LIKE` با مقادیر bound می‌سازند (مثلاً `Template::scopeSearch()`, `GeneratedDocument::scopeSearch()`)؛ هیچ SQL الحاقی از ورودی ساخته نمی‌شود.
- `DB::transaction()` برای عملیات چندمرحله‌ای (نسخه‌بندی/بازگردانی) استفاده می‌شود.
- نصب production با `APP_DEBUG=false`؛ خطاهای دیتابیس به کاربر نشان داده نمی‌شوند.

## ۹) Path Traversal و مدیریت فایل

- مسیر فایل سند از `DocumentStorageService` و با ULID ساخته می‌شود (`{document_path}/{YYYY}/{MM}/{ulid}.pdf`)؛ هیچ بخشی از آن از نام فایل/ورودی کاربر نمی‌آید.
- assetها با `handle` در جدول `assets` ارجاع می‌شوند (`asset://handle`); مسیر (`disk`, `path`) از رکورد DB خوانده می‌شود، نه از URL/پارامتر.
- handle ناموجود → خطای کنترل‌شده (نه نشت مسیر).
- دانلود سند با `ulid` و بررسی `canDownload()`/مالکیت پروژه؛ مسیر فیزیکی هرگز در URL قرار نمی‌گیرد.
- Nginx: `deny` برای فایل‌های مخفی/حساس و `location ~ /\.` (در فایل کانفیگ سایت) از دسترسی به `.env`, `.git`, `composer.*` جلوگیری می‌کند.
- آپلود asset: نام فایل/پسوند اعتبارسنجی و مسیر در کد ساخته می‌شود (`app/Services/Assets/*`).

## ۱۰) SSRF (تصاویر اینترنتی و Gotenberg)

### URL تصویر

`App\Services\Security\ExternalUrlGuard` (`assertAllowed()` + `fetchImageAsDataUri()`):

- فقط پروتکل `http`/`https`؛ URL دارای user/pass رد می‌شود.
- resolve DNS (A و AAAA) و **همهٔ** آدرس‌های resolved باید عمومی باشند؛ loopback، private، link-local، CGNAT، multicast، documentation و IPv4-mapped خصوصی رد می‌شوند.
- redirectها خودکار دنبال نمی‌شوند (`allow_redirects=false`)؛ هر redirect دستی دنبال و **دوباره** validate می‌شود (حداکثر 2 بار).
- محدودیت حجم (`PDF_MAX_REMOTE_IMAGE_BYTES`، پیش‌فرض 5MB) با بررسی `Content-Length` و طول واقعی بدنه.
- mime مجاز از `config('pdf.security.allowed_image_mimes')` (png/jpeg/webp/gif) با بررسی هدر `Content-Type` و در نبود آن `finfo`.
- timeout اتصال/دریافت کوتاه؛ خروج فقط `data:image/...;base64,...`.
- قابل خاموش‌کردن با `PDF_ALLOW_REMOTE_IMAGES=false` (کلید config: `pdf.security.allow_remote_image_variables`).
- در تست‌ها با `Http::fake()` و resolver تزریقی جایگزین می‌شود.

### Gotenberg

- فقط `GOTENBERG_URL=http://127.0.0.1:3000` (loopback)؛ در Nginx هیچ location ای به پورت 3000 proxy نمی‌شود.
- پورت 3000 نباید در فایروال عمومی باز شود؛ `ss -lntp` باید `127.0.0.1:3000` نشان دهد.
- Gotenberg هیچ اعتبارنامه‌ای برای مصرف‌کننده ندارد و هرگز از سمت مرورگر کلاینت صدا زده نمی‌شود.
- محتوای ورودی به Gotenberg از sanitizer/renderer عبور کرده و `http_errors=false`/redirect غیرفعال است.

## ۱۱) آپلود فایل (assetها)

- اعتبارسنجی در `app/Http/Requests/Admin/*` و/یا `app/Services/Assets/*`:
  - mime واقعی و پسوند مجاز (فونت/تصویر/CSS) طبق `config('pdf.security.allowed_*_mimes')`
  - حداکثر حجم `PDF_MAX_UPLOAD_KB` (پیش‌فرض 5120KB) و/یا تنظیم `security.max_upload_kb`
  - جلوگیری از نام فایل خطرناک؛ ذخیره در مسیر ساخته‌شده و بیرون از web root
  - checksum SHA-256 در `assets.checksum`
- فایل‌های نامعتبر با 422 و پیام فارسی رد می‌شوند؛ اجرای فایل از storage غیرممکن است (خارج از public + Nginx deny).

## ۱۲) موتور قالب: بدون PHP و بدون دسترسی دلخواه

- رندرر اختصاصی `App\Services\Templates\SafeTemplateRenderer` (بدون Blade/`eval`): lexer → parser → AST → `NodeRenderer`؛ فقط گرامر مستندشده را می‌پذیرد.
- ممنوعیت‌های سخت در `SafeTemplateRenderer::assertNoForbiddenConstructs()` و `TemplateSanitizer::assertNoForbiddenConstructs()`: `<?php`, `@php`, `@endphp`, `<?`, `{!!`, `@include`, `@extends`, `@inject`, `@use`, `@section`, `@yield`, `@component`, `@verbatim` و توابع اجرایی (`eval`, `exec`, `system`, `shell_exec`, `file_get_contents`, ...). در لایهٔ پنل، trait `App\Http\Requests\Admin\Concerns\ValidatesTemplateMarkup` همین موارد را روی فیلدهای `html/css/header_html/footer_html` **قبل از ذخیره** رد می‌کند و سپس موتور **قبل از رندر** دوباره بررسی/سنیتایز می‌کند (دفاع در عمق).
- فراخوانی تابع فقط برای allowlist (`ExpressionParser::ALLOWED_FUNCTIONS`: `count`, `now`, `strlen`, `mb_strlen`, `round`, `abs`, `max`, `min`, `intval`, `floatval`, `jdate`, `jdatetime`, `number`, `date`)؛ دسترسی متد/کلاس/`$GLOBALS`/backtick ممنوع (`propertyValue()` فقط آرایه/`stdClass` property می‌خواند).
- خطای سینتکس → `TemplateRenderException` با پیام فارسی و شمارهٔ خط؛ متن PHP خام هرگز به خروجی/پاسخ نشت نمی‌کند.
- حتی در صورت دور زدن sanitizer، محتوای قالب در Chromium گوتنبرگ اجرا می‌شود، نه در PHP؛ بنابراین RCE سمت سرور ممکن نیست (ایزوله‌سازی فرآیند).
- خروجی هر `{{ }}` escape می‌شود (`ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`)؛ تنها استثنا `nl2br` است که ورودی را خودش escape و سپس `<br>` درج می‌کند.

## ۱۳) ذخیره‌سازی خصوصی و URL امضاشده

- PDFها در دیسک خصوصی (`storage/app/private/...`) و خارج از `public` ذخیره می‌شوند؛ دسترسی مستقیم web وجود ندارد.
- دانلود Bearer: بررسی وضعیت `completed`، عدم انقضا (`canDownload()`) و مالکیت پروژه/کلید.
- دانلود signed: `URL::temporarySignedRoute` با TTL `PDF_SIGNED_URL_TTL` (دقیقه)؛ امضا با `APP_KEY` — تغییر `APP_KEY` همهٔ لینک‌های امضاشدهٔ قبلی را باطل می‌کند (رفتار مطلوب امنیتی).
- `expires_at` سند: پس از انقضا حتی با امضای معتبر، فایل نباید قابل دانلود باشد (کنترل سمت کنترلر + `DocumentStorageService`.
- payload سند با `'payload' => 'encrypted:array'` رمزنگاری‌شده است؛ کلید رمزنگاری = `APP_KEY` (نگهداری امن آن حیاتی است).

## ۱۴) افشای خطا و مدیریت استثنا

- `bootstrap/app.php`: `shouldRenderJsonWhen` برای همهٔ مسیرهای `api/*` → پاسخ همیشه JSON.
- `ApiController::handleApiErrors()` هر throwable داخل کنترلرهای API را می‌گیرد و با `App\Services\Api\ExceptionMapper` به `ApiException` (envelope استاندارد) تبدیل می‌کند؛ متن اصلی/نوع استثنا فقط در log سرور ثبت می‌شود. middleware `ValidateApiSignature` برای امضای نامعتبر مستقیماً JSON با `403 SIGNATURE_INVALID` برمی‌گرداند.
- در production (`APP_DEBUG=false`) پیام عمومی و کد خطای دامنه برمی‌گردد؛ stack trace/SQL/مسیر فایل هرگز به مصرف‌کننده نمی‌رسد.
- ساختار خطای یکسان: `{"success": false, "error": {"code": "...", "message": "...", "details": {}}, "request_id": "..."}`.
- کدهای ثابت نمونه: `INVALID_API_KEY`, `UNAUTHENTICATED`, `FORBIDDEN`, `TEMPLATE_NOT_FOUND`, `TEMPLATE_INACTIVE`, `TEMPLATE_NOT_ALLOWED`, `DOCUMENT_NOT_FOUND`, `DOCUMENT_NOT_READY`, `DOCUMENT_EXPIRED`, `SIGNATURE_INVALID`, `VALIDATION_ERROR`, `PAYLOAD_TOO_LARGE`, `RATE_LIMIT_EXCEEDED`, `MONTHLY_QUOTA_EXCEEDED`, `IDEMPOTENCY_KEY_INVALID`, `IDEMPOTENCY_KEY_REUSED`, `GOTENBERG_UNAVAILABLE`, `GOTENBERG_TIMEOUT`, `GOTENBERG_ERROR`, `RENDER_ERROR`, `STORAGE_ERROR`, `SERVER_ERROR`.
- خطاهای اعتبارسنجی متغیرهای قالب به‌صورت `422` با کلیدهای `data.<variable>` برمی‌گردند.
- هرگز محتوای HTML قالب، بدنهٔ درخواست، هدر `Authorization` یا کلید خام در پیام خطا/لاگ نوشته نمی‌شود.

## ۱۵) بهداشت لاگ (Logging Hygiene)

- `api_request_logs` طراحی‌شده **بدون** بدنه/هدر Authorization: `request_id`, ids, method/path, route_name, status, duration, sizes, ip, user_agent, error_code/message — ثبت در `LogApiRequest::terminate()` تا وضعیت نهایی پاسخ ثبت شود.
- پیام‌های خطای ذخیره‌شده sanitize و truncate می‌شوند: newlineها جمع می‌شوند، مسیرهای فایل/PHP با `[path]` جایگزین و طول به 500 کاراکتر محدود می‌شود (بدون stack trace/جزئیات فایل‌سیستم).
- کلید خام هرگز لاگ نمی‌شود؛ فقط `key_prefix`.
- دادهٔ متغیرهای سند (`payload`) رمزنگاری‌شده و در لاگ چاپ نمی‌شود.
- Gotenberg: فقط `error_code`/`http_status`/`attempt`/`duration_ms` و در موفقیت `duration_ms/size/assets/format`؛ محتوای HTML در لاگ نمی‌رود.
- تنظیم `LOG_LEVEL=warning` در production برای کاهش حجم و نشت؛ چرخش روزانه + logrotate.
- دسترسی به tail لاگ فقط از پنل و برای نقش `admin` (طبق قرارداد).
- `X-Request-Id` (UUID) در پاسخ و بدنهٔ JSON (`request_id`) برای همبستگی لاگ‌ها؛ در صورت نیاز به دیباگ از همین شناسه استفاده کنید، نه چاپ دادهٔ حساس.
- شکست در ثبت لاگ هرگز پاسخ API را نمی‌شکند (`terminate` با catch + `Log::warning` بدون دادهٔ حساس).

## ۱۶) وابستگی‌ها و زنجیرهٔ تأمین

- `composer.lock` و `package-lock.json` در مخزن؛ نصب تولید با `composer install --no-dev` و `npm ci` (نه `npm install`).
- به‌روزرسانی منظم امنیتی:
  ```bash
  composer audit
  npm audit --omit=dev
  composer update --with-dependencies   # با اجرای کامل تست در محیط staging
  ```
- بررسی تغییرات وابستگی‌ها در PR؛ پرهیز از پکیج‌های بدون نگه‌داری.
- PHP/Node/MySQL/Nginx/Gotenberg به‌روز نگه داشته شوند؛ Gotenberg نسخهٔ 9.x.
- `APP_KEY`/`API_KEY_HASH_SALT` در `.env` و secret manager، نه در کد/مخزن.
- دسترسی CI/CD با حداقل مجوز؛ deploy key فقط خواندن مخزن در صورت امکان.

## ۱۷) سخت‌سازی Nginx (خلاصه)

- TLS 1.2+/1.3، HSTS (پس از اطمینان از HTTPS)، `server_tokens off`.
- `deny all` برای dotfiles (`.env`, `.git`, ...)، `composer.json/lock`, `artisan`, `README`ها.
- `client_max_body_size` متناسب با `PDF_MAX_UPLOAD_KB`/payload.
- محدودیت متدهای غیرضروری (فقط GET/POST برای API/Panel).
- FastCGI timeouts هماهنگ با Gotenberg/queue.
- عدم افشای مسیر فیزیکی/نسخه (`fastcgi_hide_header X-Powered-By`).
- فایل کامل: [../deploy/nginx/pdf.as7th.com.conf](../deploy/nginx/pdf.as7th.com.conf).

## ۱۸) پاسخ به حادثه (Incident Response)

### الف) نشت کلید API

1. کلید آلوده را فوراً از پنل (`/admin` → پروژه → کلیدها) **revoke** کنید.
2. کلید جدید بسازید/چرخش کنید و در secret manager مصرف‌کننده جایگزین کنید.
3. `api_request_logs` همان کلید را از زمان احتمالی نشت بررسی کنید (IP/User-Agent/وضعیت).
4. اسناد تولیدشدهٔ مشکوک را بررسی/حذف کنید.
5. در صورت نشت گسترده: چرخش همهٔ کلیدهای پروژه + اطلاع‌رسانی به مالکان.

### ب) نشت `APP_KEY` / `API_KEY_HASH_SALT`

- `APP_KEY` افشاشده: **همهٔ** payloadهای رمزنگاری‌شده و لینک‌های امضاشده در خطر هستند.
  1. کلیدهای API را باطل/دوباره صادر کنید.
  2. `APP_KEY` جدید تولید کنید (`php artisan key:generate`) — توجه: payloadهای قبلی قابل خواندن نیستند؛ در صورت نیاز ابتدا رمزگشایی/آرشیو کنید.
  3. لینک‌های امضاشدهٔ قدیمی خودبه‌خود باطل می‌شوند.
- `API_KEY_HASH_SALT` افشاشده: با دانستن salt و hash می‌توان حملهٔ حدس را تسهیل کرد؛ salt جدید + صدور مجدد همهٔ کلیدها.

### ج) سوءاستفاده/حملهٔ فعال

1. Rate limit/بلاک موقت در Nginx یا فایروال برای IP.
2. بررسی `api_request_logs`, `document_jobs`, لاگ‌های Nginx/PHP.
3. در صورت نیاز، پروژه/کلید را غیرفعال کنید (بدون حذف داده).
4. بکاپ/فورنزیک: کپی لاگ‌ها و دیتابیس برای بررسی.
5. رفع ریشه‌ای، سپس بازگرداندن سرویس و گزارش.

### د) نفوذ/آلایش سرور

- سرور را از شبکه خارج کنید، بکاپ امن تهیه کنید، اعتبارنامه‌ها (DB/API/SSH) را بچرخانید، از بکاپ تمیز بازگردانی کنید، سیستم را بازسازی و patch کنید.

### ه) افشای دادهٔ اسناد

- اسناد مرتبط را حذف/غیرفعال کنید، پروژهٔ مربوطه را غیرفعال کنید، مشتریان را طبق سیاست سازمان مطلع کنید، و دسترسی storage/بکاپ‌ها را بازبینی کنید.

## ۱۹) چک‌لیست امنیتی استقرار

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` و `API_KEY_HASH_SALT` یکتا و پایدار؛ در secret manager
- [ ] `.env` با مجوز 640 و مالک www-data؛ خارج از web root
- [ ] HTTPS اجباری، HSTS، کوکی secure
- [ ] Gotenberg فقط `127.0.0.1:3000`؛ فایروال پورت 3000 بسته
- [ ] `php-fpm` با `expose_php=Off`, `request_terminate_timeout` مناسب
- [ ] Nginx deny dotfiles/حساس؛ `server_tokens off`
- [ ] کاربر DB حداقل دسترسی و رمز قوی
- [ ] رمز مدیر پیش‌فرض تغییر کرده؛ کاربران غیرفعال حذف/غیرفعال
- [ ] کلیدهای API پروژه‌محور و با انقضا در صورت نیاز
- [ ] backup رمزنگاری‌شده + تست بازیابی
- [ ] `composer audit` / `npm audit` دوره‌ای
- [ ] لاگ‌ها بدون دادهٔ حساس؛ logrotate فعال
- [ ] مانیتورینگ نرخ 401/429/5xx و failed jobs

</div>
