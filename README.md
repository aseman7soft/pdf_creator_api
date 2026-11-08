<div dir="rtl">

# سرویس مرکزی تولید اسناد و PDF

سرویس مرکزی تولید سند و PDF برای پروژه‌های سازمانی؛ ساخته‌شده با **Laravel 13**، **PHP 8.5**، **MySQL 8** و **Gotenberg 9** (موتور Chromium). این سرویس قالب‌های HTML/CSS ذخیره‌شده در دیتابیس را بدون اجرای PHP و بدون دسترسی به فایل‌سیستم/شبکهٔ دلخواه رندر می‌کند و خروجی PDF را از طریق یک REST API نسخه‌دار (`/api/v1`) و یک پنل مدیریت فارسی (`/admin`) در اختیار مصرف‌کننده‌ها قرار می‌دهد.

- دامنه تولید: <https://pdf.as7th.com>
- نسخه API: `v1` — مسیر پایه: `/api/v1`
- پنل مدیریت: `/admin`

## فهرست مطالب

- [امکانات](#امکانات)
- [معماری کلی](#معماری-کلی)
- [پیش‌نیازها](#پیشنیازها)
- [نصب سریع لوکال](#نصب-سریع-لوکال)
  - [ویندوز (Laravel Herd)](#ویندوز-laravel-herd)
  - [اوبونتو / لینوکس](#اوبونتو--لینوکس)
- [تنظیمات محیطی (env)](#تنظیمات-محیطی-env)
- [دیتابیس، مهاجرت و دادهٔ اولیه](#دیتابیس-مهاجرت-و-دادهٔ-اولیه)
- [ساخت کاربر مدیر و API Key](#ساخت-کاربر-مدیر-و-api-key)
- [اجرای صف (Queue Worker)](#اجرای-صف-queue-worker)
- [ساخت asset‌های فرانت‌اند](#ساخت-assetهای-فرانتاند)
- [اجرای سرویس](#اجرای-سرویس)
- [تست‌ها](#تستها)
- [شروع سریع با API](#شروع-سریع-با-api)
- [ساختار پروژه](#ساختار-پروژه)
- [تست یکپارچه (Integration)](#تست-یکپارچه-integration)
- [مستندات](#مستندات)
- [محدودیت‌ها و نکات](#محدودیتها-و-نکات)
- [مجوز](#مجوز)

## امکانات

### موتور قالب امن
- رندر قالب‌های ذخیره‌شده در دیتابیس با سینتکس Blade-like و **بدون اجرای PHP**:
  - `{{ expression }}` با escape خودکار HTML و فیلترهای `number`, `jdate`, `jdatetime`, `upper`, `lower`, `trim`, `nl2br`, `default`, `truncate`, `json`
  - کنترل‌های `@if/@elseif/@else/@endif`, `@unless`, `@foreach` (با متغیر `loop`), `@isset`, `@empty`, `@jdate`, `@date`, `@number` و کامنت `{{-- --}}`
  - دسترسی نقطه‌ای/اندیسی (`guest.name`, `items[0]`)، `??` و عملگرهای مقایسه/منطقی/ریاضی
  - فقط توابع allowlist (`count`, `now`, `strlen`, `mb_strlen`, `round`, `abs`, `max`, `min`, `intval`, `floatval`, `jdate`, `jdatetime`, `number`, `date`)؛ هیچ متد/کلاس/`eval`/`$GLOBALS` مجاز نیست
- سنیتایزر و اعتبارسنج قالب: رد `<?php`, `@php`, `@endphp`, `<?`, `{!!}`, `@include`, `@extends`, `@inject`, `@use` و حذف `<script>`/`<iframe>`/`<object>`/`<embed>`، attribute‌های `on*` و `javascript:`
- اعتبارسنجی متغیرهای قالب با نوع، الزام، طول، بازه و قواعد اعتبارسنجی؛ خطاها به‌صورت 422 استاندارد API برمی‌گردند
- پشتیبانی کامل از فارسی/RTL و فونت‌های Vazirmatn (به‌صورت asset ثبت‌شده با handleهای `vazirmatn-regular` و `vazirmatn-bold`)
- ارجاع به assetهای داخلی با پروتکل امن `asset://handle` و پشتیبانی از تصاویر `data:` و URL عمومی (با محافظ SSRF)

### API نسخه‌دار (v1)
- احراز هویت Bearer با API Key (ذخیره‌شده به‌صورت HMAC-SHA256 — کلید خام فقط یک‌بار نمایش داده می‌شود)
- تولید هم‌زمان PDF (`POST /api/v1/documents/generate`) با خروجی مستقیم `application/pdf`
- تولید غیرهم‌زمان با صف (`POST /api/v1/documents/queue`) و پیگیری وضعیت/دانلود
- دانلود با Bearer و دانلود با **temporary signed URL** (بدون Bearer)
- کنترل دسترسی پروژه به قالب‌ها (`api_project_template`؛ پروژه بدون محدودیت = همهٔ قالب‌های فعال)
- Rate limit در سطح کلید/پروژه/سقف ماهانه، پاسخ 429 با هدرهای `X-RateLimit-*` و `Retry-After`
- Idempotency با هدر اختیاری `Idempotency-Key`
- محدودیت حجم payload و `request_id` یکتا در پاسخ (`X-Request-Id`)
- صفحه Swagger UI و فایل OpenAPI

### پنل مدیریت فارسی (`/admin`)
- ورود/خروج با session و throttle؛ جلوگیری از ورود کاربر غیرفعال
- داشبورد آماری (اسناد، درخواست‌ها، موفق/ناموفق، میانگین زمان تولید)
- مدیریت کامل قالب‌ها: ایجاد/ویرایش (HTML/CSS/هدر/فوتر + تنظیمات صفحه)، متغیرها، کپی، فعال/غیرفعال، پیش‌نمایش، **نسخه‌بندی و بازگردانی**
- مدیریت پروژه‌ها و کلیدهای API (ساخت/چرخش/لغو/انقضا، نمایش یک‌بارهٔ کلید)
- مدیریت اسناد (جستجو، فیلتر، دانلود، حذف، retry)
- صف: `document_jobs` و `failed_jobs` با retry/delete
- لاگ درخواست‌های API و tail فایل لاگ (فقط admin)
- مدیریت assetها (فونت/تصویر/CSS) با اعتبارسنجی mime/پسوند/حجم
- تنظیمات (general/documents/api/gotenberg/security/queue) و تست اتصال Gotenberg
- مدیریت کاربران با نقش‌های `admin` / `manager` / `viewer`

### امنیت و نگهداری
- ذخیره‌سازی **خصوصی** فایل PDFها در `storage/app/private/documents/{YYYY}/{MM}/{ulid}.pdf` (هرگز در `public`)
- retry/backoff/timeout قابل تنظیم برای صف و Gotenberg؛ ثبتattemptها و `duration_ms` در `document_jobs`
- خطاهای ثابت (`GOTENBERG_UNAVAILABLE`, `GOTENBERG_TIMEOUT`, `RENDER_ERROR`, ...) و پیام عمومی در production
- پاک‌سازی خودکار اسناد منقضی بر اساس `PDF_DOCUMENT_RETENTION_DAYS`
- Redis به‌صورت اختیاری برای queue/cache

## معماری کلی

```text
مصرف‌کننده‌ها (پروژه‌ها) ──HTTPS / Bearer──▶ Nginx ──▶ PHP 8.5-FPM (Laravel)
                                                    │
                                                    ├─ API v1 ──▶ RateLimit / Idempotency / ApiRequestLog
                                                    │                 │
                                                    │                 ├─ sync  ─▶ Render ─▶ Gotenberg ─▶ Storage (private)
                                                    │                 └─ async ─▶ Queue (documents) ─▶ GenerateDocumentJob ─▶ Gotenberg ─▶ Storage
                                                    │
                                                    └─ پنل /admin (session, فارسی/RTL)

MySQL 8  ──  داده‌های قالب/پروژه/کلید/سند/ردیابی صف/لاگ
Gotenberg 9 (127.0.0.1:3000) ── فقط از طریق loopback
```

جزئیات کامل در [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## پیش‌نیازها

| نیازمندی | نسخه پیشنهادی | توضیح |
|---|---|---|
| PHP | **8.5** (حداقل 8.3 طبق `composer.json`) | افزونه‌های `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `intl` |
| Composer | 2.x | نصب وابستگی‌ها |
| MySQL | 8.0+ | دیتابیس `pdf_service` با کاراکترست `utf8mb4` |
| Node.js | 20.19+ یا 22.12+ (Vite 7) | ساخت assetهای پنل |
| Gotenberg | 9.x | موتور Chromium؛ روی loopback پورت `3000` |
| Redis | اختیاری | برای queue/cache در بار زیاد |
| Nginx + PHP-FPM | — | فقط برای استقرار (لوکال با Herd) |

> Gotenberg در محیط توسعهٔ این مخزن نصب نیست؛ تست‌های PHPUnit همیشه با `Http::fake()` اجرا می‌شوند و تست واقعی Gotenberg فقط از طریق اسکریپت یکپارچه انجام می‌شود.

## نصب سریع لوکال

### ویندوز (Laravel Herd)

> در این ماشین مسیر PHP 8.5 به‌صورت صریح استفاده می‌شود:
>
> ```powershell
> $php = "C:\Users\softa\.config\herd\bin\php85\php.exe"
> & $php -v   # باید PHP 8.5.x نشان دهد
> ```
>
> اگر `php` روی PATH هم 8.5 است می‌توانید همان `php` را بنویسید؛ در غیر این صورت از مسیر بالا استفاده کنید.

1) پروژه را دریافت و وارد پوشه شوید:

```powershell
cd E:\projects\pdf_creator_api
```

2) نصب وابستگی‌ها و آماده‌سازی اولیه:

```powershell
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"
composer install
Copy-Item .env.example .env
& $php artisan key:generate
```

3) دیتابیس لوکال را بسازید (MySQL 8):

```sql
CREATE DATABASE pdf_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4) در `.env` مقادیر لوکال را تنظیم کنید (نمونه):

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=https://pdf-creator.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pdf_service
DB_USERNAME=root
DB_PASSWORD=         # رمز MySQL لوکال خودتان

SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=database
CACHE_STORE=database
GOTENBERG_URL=http://127.0.0.1:3000
API_KEY_HASH_SALT=   # خروجی: openssl rand -hex 32  (در لوکال ثابت نگه دارید)
```

5) مهاجرت و دادهٔ اولیه:

```powershell
& $php artisan migrate --seed
```

این دستور `SettingsSeeder`، `AdminUserSeeder`، `AssetSeeder` (فونت‌های Vazirmatn)، `TemplateSeeder` (قالب‌های نمونهٔ `hotel-voucher`، `invoice-standard`، `tour-itinerary`) و در محیط `local`/`testing` پروژهٔ نمایشی `demo-project` را می‌سازد. کلید API نمایشی در خروجی `db:seed` **یک‌بار** چاپ می‌شود.

6) ساخت assetهای فرانت‌اند:

```powershell
npm install
npm run build
```

7) اجرای سرویس (Herd): سایت `https://pdf-creator.test` را در Herd بسازید یا:

```powershell
& $php artisan serve
```

8) اجرای صف در ترمینال جداگانه:

```powershell
& $php artisan queue:work --queue=documents --tries=3 --timeout=180 --backoff=15
```

سپس وارد `https://pdf-creator.test/admin` شوید. دسترسی‌های Herd و مسیر PHP توسط پنل Herd مدیریت می‌شوند.

> **میان‌بر:** اسکریپت آمادهٔ Composer نیز وجود دارد (`composer run setup`) که `composer install`، ساخت `.env`، `key:generate`، `migrate --force`، `npm install` و `npm run build` را انجام می‌دهد — اما seed و اجرای queue را شامل نمی‌شود.

### اوبونتو / لینوکس

نصب کامل (PHP 8.5-FPM، MySQL 8، Nginx، Gotenberg 9 روی loopback، مجوزها، systemd، SSL و ...) در [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) آمده است. خلاصهٔ سریع برای توسعه:

```bash
git clone <repo-url> pdf-service && cd pdf-service
composer install
cp .env.example .env
php artisan key:generate
# .env را ویرایش کنید (DB، GOTENBERG_URL، API_KEY_HASH_SALT)
php artisan migrate --seed
npm ci && npm run build
php artisan serve            # یا Nginx + PHP-FPM
php artisan queue:work --queue=documents --tries=3 --timeout=180 --backoff=15
```

## تنظیمات محیطی (env)

مرجع کامل و کامنت‌دار: [`.env.example`](.env.example). نمونهٔ تولید: [`deploy/.env.production.example`](deploy/.env.production.example).

### برنامه

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `APP_NAME` | `Central PDF Service` | نام نمایشی سرویس |
| `APP_ENV` | `production` (لوکال: `local`) | محیط اجرا |
| `APP_DEBUG` | `false` | در production حتماً `false` |
| `APP_KEY` | — | با `php artisan key:generate` ساخته می‌شود |
| `APP_URL` | `https://pdf.as7th.com` | آدرس پایه (برای URLهای امضاشده و لینک‌های مطلق) |
| `APP_TIMEZONE` | `Asia/Tehran` | منطقهٔ زمانی محاسبات شمسی |

### دیتابیس / session / صف / cache

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `DB_CONNECTION` | `mysql` | درایور دیتابیس |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` | `127.0.0.1` / `3306` / `pdf_service` | اتصال MySQL |
| `DB_USERNAME` / `DB_PASSWORD` | `pdf_service` / — | کاربر اختصاصی دیتابیس (نه root) |
| `SESSION_DRIVER` | `database` | نشست پنل |
| `SESSION_SECURE_COOKIE` | `true` در production | کوکی فقط روی HTTPS |
| `QUEUE_CONNECTION` | `database` | `database` یا `redis` |
| `CACHE_STORE` | `database` | `database` یا `redis` |
| `REDIS_*` | `127.0.0.1:6379` | فقط در صورت استفاده از Redis |

### Gotenberg

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `GOTENBERG_URL` | `http://127.0.0.1:3000` | **فقط loopback**؛ هرگز عمومی نشود |
| `GOTENBERG_CONNECT_TIMEOUT` | `10` | ثانیه |
| `GOTENBERG_TIMEOUT` | `90` | ثانیه |
| `GOTENBERG_RETRY_TIMES` | `1` | تعداد تلاش مجدد HTTP |
| `GOTENBERG_RETRY_SLEEP_MS` | `500` | فاصلهٔ تلاش‌ها |

### اسناد و ذخیره‌سازی

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `PDF_DISK` | `local` | دیسک خصوصی ذخیرهٔ PDF |
| `PDF_DOCUMENT_PATH` | `documents` | مسیر نسبی روی دیسک |
| `PDF_DEFAULT_FORMAT` | `A4` | اندازهٔ صفحهٔ پیش‌فرض |
| `PDF_DEFAULT_ORIENTATION` | `portrait` | جهت صفحه |
| `PDF_DOCUMENT_RETENTION_DAYS` | `30` | مدت نگهداری فایل |
| `PDF_MAX_PAYLOAD_KB` | `1024` | سقف payload ورودی API |
| `PDF_MAX_TEMPLATE_BYTES` | `2097152` | سقف حجم قالب |
| `PDF_MAX_UPLOAD_KB` | `5120` | سقف آپلود asset |
| `PDF_SIGNED_URL_TTL` | `30` | اعتبار لینک امضاشده (دقیقه) |

### صف

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `PDF_QUEUE_CONNECTION` | خالی (→ `QUEUE_CONNECTION`) | اتصال اختصاصی صف اسناد |
| `PDF_QUEUE_NAME` | `documents` | نام صف |
| `PDF_QUEUE_TRIES` | `3` | حداکثر تلاش job |
| `PDF_QUEUE_TIMEOUT` | `180` | timeout هر job (ثانیه) |
| `PDF_QUEUE_BACKOFF` | `15` | فاصلهٔ تلاش مجدد (ثانیه) |

### API و کلیدها

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `API_RATE_LIMIT_PER_MINUTE` | `60` | سقف پیش‌فرض هر کلید |
| `API_KEY_PREFIX` | `pdfsvc` | پیشوند کلید (برای شناسایی در لاگ) |
| `API_IDEMPOTENCY_TTL_HOURS` | `24` | اعتبار `Idempotency-Key` |
| `API_KEY_HASH_SALT` | — | نمک HMAC کلیدها؛ **ثابت بماند** وگرنه همهٔ کلیدها بی‌اعتبار می‌شوند. تولید: `openssl rand -hex 32` |

### ساخت مدیر اولیه

| متغیر | توضیح |
|---|---|
| `ADMIN_EMAIL` | ایمیل مدیر ساخته‌شده توسط seeder |
| `ADMIN_PASSWORD` | رمز مدیر؛ اگر خالی باشد یک رمز تصادفی ساخته و **یک‌بار** چاپ می‌شود |

## دیتابیس، مهاجرت و دادهٔ اولیه

```powershell
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"
& $php artisan migrate            # اجرای مهاجرت‌ها
& $php artisan migrate --seed     # + دادهٔ اولیه
& $php artisan migrate --force    # در production (بدون تأیید تعاملی)
& $php artisan db:seed            # اجرای کامل DatabaseSeeder
& $php artisan db:seed --class=TemplateSeeder   # فقط قالب‌های نمونه
```

- migrate از دیتابیس فعلی (`.env`) استفاده می‌کند. تست‌ها روی SQLite حافظه‌ای طبق `phpunit.xml` اجرا می‌شوند.
- در production از `--force` استفاده کنید (اسکریپت `deploy/deploy.sh` این کار را انجام می‌دهد).
- seed در تولید فقط تنظیمات، مدیر، فونت‌ها و قالب‌های نمونه را می‌سازد؛ پروژهٔ نمایشی فقط در `local`/`testing` ساخته می‌شود.

## ساخت کاربر مدیر و API Key

### مدیر

- راه اول (seed): `ADMIN_EMAIL` و `ADMIN_PASSWORD` را در `.env` تنظیم و `php artisan db:seed --class=AdminUserSeeder` را اجرا کنید. اگر `ADMIN_PASSWORD` خالی باشد، رمز تصادفی تولید و یک‌بار چاپ می‌شود.
- راه دوم (پنل): با یک کاربر `admin` وارد `/admin/users` شوید و کاربر جدید بسازید.
- نقش‌ها: `admin` (کامل)، `manager` (قالب/پروژه/سند/صف)، `viewer` (فقط مشاهده/دانلود).

### API Key

کلید خام **هرگز در دیتابیس ذخیره نمی‌شود**؛ فقط `key_prefix` و `key_hash` (HMAC-SHA256) ذخیره می‌شوند. ساخت کلید از پنل:

1. وارد `/admin` شوید → «پروژه‌ها» → پروژهٔ موردنظر → «کلیدها» → «ساخت کلید».
2. کلید تولیدشده فقط **یک‌بار** در همان درخواست نمایش داده می‌شود؛ آن را در جای امن (مثلاً secret manager) نگه دارید.
3. برای چرخش: «چرخش» (کلید قبلی revoke و کلید جدید ساخته می‌شود) یا «لغو».
4. برای اتصال قالب‌ها به پروژه: در صفحهٔ پروژه، قالب‌های مجاز را انتخاب کنید. پروژهٔ بدون قالب متصل، به همهٔ قالب‌های فعال دسترسی دارد.

در محیط `local`، اگر پروژهٔ نمایشی seed شود، کلید نمایشی یک‌بار در خروجی `php artisan db:seed` چاپ می‌شود.

## اجرای صف (Queue Worker)

تولید غیرهم‌زمان روی صف `documents` اجرا می‌شود:

```powershell
# لوکال (ویندوز)
& $php artisan queue:work --queue=documents --tries=3 --timeout=180 --backoff=15

# لینوکس
php artisan queue:work --queue=documents --tries=3 --timeout=180 --backoff=15
```

- مقادیر `--tries/--timeout/--backoff` باید با `PDF_QUEUE_TRIES` / `PDF_QUEUE_TIMEOUT` / `PDF_QUEUE_BACKOFF` هماهنگ باشند.
- اگر `PDF_QUEUE_CONNECTION` یا `QUEUE_CONNECTION=redis` است، worker از همان اتصال استفاده می‌کند.
- در production از سرویس systemd استفاده کنید: [`deploy/systemd/pdf-queue.service`](deploy/systemd/pdf-queue.service).
- مشاهده/retry: `/admin/queue` یا دستورات `php artisan queue:failed` و `php artisan queue:retry all`.
- بعد از هر استقرار: `php artisan queue:restart` (تا workerها کد جدید را بارگذاری کنند).

### Timeout و retry

`GenerateDocumentJob` تعداد تلاش/زمان/فاصلهٔ retry را از config می‌گیرد؛ مقادیر `--tries/--timeout/--backoff` در دستور worker باید با `PDF_QUEUE_*` هماهنگ باشند (پیش‌فرض‌ها: 3 / 180 / 15). خطاهای غیرقابل retry (`TEMPLATE_NOT_FOUND`, `TEMPLATE_INACTIVE`, `VALIDATION_ERROR`, `RENDER_ERROR`) در همان تلاش اول نهایی می‌شوند؛ خطاهای `GOTENBERG_*`/`STORAGE_ERROR` تا اتمام تلاش‌ها retry می‌شوند.

### زمان‌بندی (Scheduler)

برخی کارهای نگهداری در `routes/console.php` زمان‌بندی شده‌اند (حذف روزانهٔ اسناد منقضی 03:30، `pdf:health` ساعتی، `queue:prune-failed` روزانه). برای اجرا:

```powershell
# لوکال
& $php artisan schedule:work

# production (systemd timer)
# sudo systemctl enable --now pdf-scheduler.timer
```

یا cron هر دقیقه: `* * * * * cd /var/www/pdf-service && php artisan schedule:run >> /dev/null 2>&1`.

## ساخت assetهای فرانت‌اند

```powershell
npm install          # لوکال
npm ci               # production (نصب دقیق از lockfile)

npm run build        # خروجی در public/build
npm run dev          # حالت توسعه (Vite HMR)
```

- پنل به‌صورت شرطی asset بارگذاری می‌کند: اگر `public/build/manifest.json` موجود باشد از خروجی Vite استفاده می‌شود؛ در غیر این صورت از assetهای از پیش موجود `public/vendor/` (Bootstrap RTL، Bootstrap Icons، Vazirmatn، `admin.css` و `panel.js`). build برای assets به‌روز توصیه می‌شود ولی پیش‌نیاز مطلق نیست.
- در production باید `npm ci && npm run build` در مرحلهٔ deploy اجرا شود (یا `public/build` از CI منتقل شود)؛ `public/build` در `.gitignore` است.

## اجرای سرویس

- **لوکال (Herd):** سایت `pdf-creator.test` → در مرورگر `https://pdf-creator.test/admin`.
- **لوکال بدون Herd:**
  ```powershell
  & $php artisan serve
  ```
- **تولید:** Nginx + PHP 8.5-FPM روی `https://pdf.as7th.com` (راهنما: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)).
- **بررسی سلامت:**
  - `GET /up` — health route پیش‌فرض Laravel
  - `GET /health` — پاسخ JSON سادهٔ سرویس (بدون auth)
  - `GET /api/v1/health` — وضعیت سرویس/DB/Gotenberg (بدون جزئیات حساس، پاسخ ۶۰ ثانیه کش می‌شود):
    ```json
    {"success": true, "data": {"status": "ok", "service": "pdf-service", "version": "v1", "timestamp": "...", "checks": {"database": "ok", "gotenberg": "ok"}}}
    ```
    اگر یکی از اجزا خطا بدهد `status` برابر `degraded` می‌شود (پایین بودن Gotenberg باعث خطای 500 نمی‌شود).
  ```bash
  curl -s https://pdf.as7th.com/up
  curl -s https://pdf.as7th.com/api/v1/health
  ```

## تست‌ها

```powershell
$php = "C:\Users\softa\.config\herd\bin\php85\php.exe"

& $php artisan test                                   # کل تست‌ها
& $php artisan test --filter=Something                # فیلتر
& $php vendor\bin\phpunit tests/Feature/Api           # یک پوشه
& $php vendor\bin\phpunit --testsuite=Unit            # یک suite
```

```bash
# لینوکس
php artisan test
php vendor/bin/phpunit tests/Feature/Api
```

- PHPUnit 12 با `RefreshDatabase`؛ دیتابیس تست SQLite حافظه‌ای (`phpunit.xml`).
- Gotenberg همیشه `Http::fake()` می‌شود؛ Storage در تست‌ها `Storage::fake('local')`.
- پیش از اجرا در صورت تغییر env: `php artisan config:clear`.

## شروع سریع با API

پیشوند همهٔ مسیرها: `/api/v1` — جزئیات کامل: [docs/openapi.yaml](docs/openapi.yaml) و [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

```bash
export PDF_BASE_URL="https://pdf.as7th.com"
export PDF_API_KEY="pdfsvc_xxx"   # کلید خام؛ هرگز در git قرار ندهید

# 1) سلامت
curl -s "$PDF_BASE_URL/api/v1/health"

# 2) لیست قالب‌های مجاز پروژه
curl -s -H "Authorization: Bearer $PDF_API_KEY" \
  "$PDF_BASE_URL/api/v1/templates"

# 3) تولید هم‌زمان PDF (خروجی فایل)
curl -s -D headers.txt -o voucher.pdf \
  -H "Authorization: Bearer $PDF_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "template": "hotel-voucher",
    "reference": "BOOK-1001",
    "data": {
      "guest_name": "علی رضایی",
      "hotel_name": "هتل نمونه",
      "booking_reference": "BOOK-1001",
      "check_in": "2026-11-20",
      "check_out": "2026-11-23",
      "room_count": 2,
      "total_price": 12500000,
      "currency": "IRR",
      "guests": [
        {"name": "علی رضایی", "passport": "A1234567"}
      ]
    }
  }' \
  "$PDF_BASE_URL/api/v1/documents/generate"

# 4) تولید غیرهم‌زمان
curl -s -H "Authorization: Bearer $PDF_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"template":"hotel-voucher","reference":"BOOK-1002","data":{...}}' \
  "$PDF_BASE_URL/api/v1/documents/queue"

# 5) وضعیت / دانلود (ulid از پاسخ قبلی)
curl -s -H "Authorization: Bearer $PDF_API_KEY" \
  "$PDF_BASE_URL/api/v1/documents/{ulid}/status"
curl -s -L -o document.pdf \
  "$PDF_BASE_URL/api/v1/documents/{ulid}/download/signed?expires=...&signature=..."
```

- بدنهٔ `data` باید object باشد؛ حداکثر حجم طبق `PDF_MAX_PAYLOAD_KB` (پیش‌فرض 1MB؛ بیشتر → `413 PAYLOAD_TOO_LARGE`).
- پاسخ موفق: `{"success": true, "data": {...}, "meta": {...}, "request_id": "..."}`.
- خطاها ساختار یکسان دارند: `{"success": false, "error": {"code": "...", "message": "...", "details": {}}, "request_id": "..."}`.
- کدهای خطای رایج: `INVALID_API_KEY`، `RATE_LIMIT_EXCEEDED`، `MONTHLY_QUOTA_EXCEEDED`، `PAYLOAD_TOO_LARGE`، `VALIDATION_ERROR`، `TEMPLATE_NOT_FOUND`، `TEMPLATE_INACTIVE`، `TEMPLATE_NOT_ALLOWED`، `DOCUMENT_NOT_FOUND`، `DOCUMENT_NOT_READY`، `DOCUMENT_EXPIRED`، `SIGNATURE_INVALID`، `IDEMPOTENCY_KEY_REUSED`، `GOTENBERG_UNAVAILABLE`، `GOTENBERG_TIMEOUT`، `GOTENBERG_ERROR`، `RENDER_ERROR`، `STORAGE_ERROR`.
- هدرهای مفید: `X-Document-Id`، `X-Generation-Time-Ms`، `X-Request-Id`، و در 429: `Retry-After` و `X-RateLimit-Limit/Remaining/Reset`.
- `/api/v1/templates` صفحه‌بندی‌شده است (`page`, `per_page`, `search`, `category` در query) و `meta` شامل `current_page/per_page/total/last_page` است.
- Idempotency با هدر `Idempotency-Key` (یا `X-Idempotency-Key`): همان کلید + همان بدنه همان نتیجه را برمی‌گرداند؛ بدنهٔ متفاوت → `IDEMPOTENCY_KEY_REUSED` (422).

## ساختار پروژه

```text
pdf_creator_api/
├── app/
│   ├── Console/Commands/          # دستورات artisan (سلامت/نگهداری)
│   ├── Enums/                     # نقش‌ها، وضعیت‌ها، انواع متغیر/asset/صفحه
│   ├── Events/                    # DocumentGenerated / DocumentGenerationFailed
│   ├── Exceptions/                # خطاهای دامنه (Gotenberg، قالب، روان‌سازی)
│   ├── Http/
│   │   ├── Controllers/Api/       # کنترلرهای API v1
│   │   ├── Controllers/Admin/     # کنترلرهای پنل
│   │   ├── Controllers/Auth/      # ورود/خروج
│   │   ├── Middleware/            # احراز هویت API، rate limit، لاگ، payload
│   │   ├── Requests/              # FormRequestهای API و پنل
│   │   └── Resources/             # API Resources
│   ├── Jobs/GenerateDocumentJob.php
│   ├── Models/                    # Template, GeneratedDocument, ApiKey, ...
│   ├── Policies/                  # مجوزهای نقش‌محور
│   ├── Services/
│   │   ├── Api/                   # ApiKeyService + سرویس‌های API
│   │   ├── Assets/                # آپلود و مدیریت asset
│   │   ├── Documents/             # DocumentGenerationService, DocumentStorageService
│   │   ├── Gotenberg/             # کلاینت Gotenberg
│   │   ├── Security/              # ExternalUrlGuard (SSRF)
│   │   ├── Settings/              # SettingsService
│   │   └── Templates/             # رندرر امن، سنیتایزر، اعتبارسنج متغیرها
│   └── Support/                   # helpers (jdate/fa_num/setting) و Jalali
├── bootstrap/app.php              # ثبت middleware و مسیرها
├── config/                        # api.php, pdf.php, ...
├── database/
│   ├── factories/
│   ├── migrations/                # ساختار کامل داده
│   └── seeders/                   # AdminUser, Settings, Asset, Template, DemoApiProject
├── deploy/
│   ├── nginx/pdf.as7th.com.conf
│   ├── systemd/pdf-queue.service, pdf-scheduler.service, pdf-scheduler.timer
│   ├── deploy.sh
│   └── .env.production.example
├── docs/                          # CONTRACTS, ARCHITECTURE, DEPLOYMENT, SECURITY, OPERATIONS, TESTING, openapi.yaml
├── public/                        # تنها web root؛ index.php + build (Vite) + vendor (fallback assets)
├── resources/                     # views (پنل/API)، css، js، fonts
├── routes/                        # api.php, web.php, console.php (زمان‌بندی‌ها)
├── scripts/integration-test.ps1   # تست واقعی Gotenberg (ویندوز)
├── scripts/integration-test.sh    # تست واقعی Gotenberg (لینوکس)
├── storage/                       # app/private (PDFها)، framework، logs
└── tests/                         # Unit + Feature (PHPUnit 12)
```

## تست یکپارچه (Integration)

تست واقعی سرویس با Gotenberg واقعی (نه mock) برای اعتبارسنجی end-to-end:

```powershell
# ویندوز
$env:PDF_BASE_URL = "https://pdf-creator.test"
$env:PDF_API_KEY = "pdfsvc_xxx"   # کلید خام؛ در فایل ذخیره نکنید
powershell -ExecutionPolicy Bypass -File .\scripts\integration-test.ps1
```

```bash
# لینوکس
export PDF_BASE_URL="https://pdf.as7th.com"
export PDF_API_KEY="pdfsvc_xxx"
./scripts/integration-test.sh
```

اسکریپت موارد سلامت، تولید هم‌زمان (assert هدر `%PDF`)، رندر قالب فارسی، قالب چندصفحه‌ای، صف، دانلود امضاشده و (اختیاری) rate limit را بررسی می‌کند. جزئیات در [docs/TESTING.md](docs/TESTING.md).

## مستندات

| سند | توضیح |
|---|---|
| [docs/CONTRACTS.md](docs/CONTRACTS.md) | قراردادهای پیاده‌سازی و مالکیت فایل‌ها (مرجع تیم) |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | معماری، جریان درخواست، موتور امن قالب، مدل داده، تصمیم‌ها |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | استقرار روی Ubuntu 22.04 + Nginx + PHP 8.5-FPM + MySQL 8 + Gotenberg 9 |
| [docs/SECURITY.md](docs/SECURITY.md) | مدل تهدید و کنترل‌ها (API key، XSS، SSRF، path traversal، ...) |
| [docs/OPERATIONS.md](docs/OPERATIONS.md) | رانبوک عملیات: مانیتورینگ، صف، پاک‌سازی، پشتیبان‌گیری، بازیابی |
| [docs/TESTING.md](docs/TESTING.md) | راهنمای تست‌های PHPUnit و اسکریپت یکپارچه |
| [docs/openapi.yaml](docs/openapi.yaml) | مشخصات OpenAPI برای API v1 |

دسترسی‌های سریع: پنل `/admin`، Swagger UI `/api/v1/docs`، فایل OpenAPI `/api/v1/openapi.yaml`، سلامت `/up` و `/api/v1/health`.

## محدودیت‌ها و نکات

- **قالب‌ها فقط با سینتکس امن رندر می‌شوند**؛ PHP، `@php`، `@include`، `@extends`، `@inject`، `{!! !!}` و اسکریپت مجاز نیست. اگر قالب موجود از این موارد استفاده کند، ذخیره/رندر آن با خطای قابل‌نمایش رد می‌شود.
- **Gotenberg فقط روی loopback** قابل دسترسی است و هیچ پورت عمومی برای آن باز نمی‌شود؛ نبودن آن باعث خطای `GOTENBERG_UNAVAILABLE`/`GOTENBERG_TIMEOUT` و ثبت در `document_jobs` می‌شود.
- **فایل‌های PDF عمومی نیستند**؛ دانلود فقط با Bearer مجاز یا signed URL موقت. لینک مستقیم `storage` وجود ندارد.
- تولید انتخاب‌شده فقط از موتور Chromium گوتنبرگ پشتیبانی می‌کند (HTML/CSS → PDF)؛ خروجی‌های Word/Excel در محدودهٔ این سرویس نیست.
- فایل‌های PDF پس از `PDF_DOCUMENT_RETENTION_DAYS` منقضی و با دستور پاک‌سازی حذف می‌شوند؛ برای نگهداری بلندمدت باید خودتان آرشیو کنید.
- تست‌های خودکار به Gotenberg واقعی وابسته نیستند؛ برای اعتبارسنجی موتور واقعی از اسکریپت یکپارچه استفاده کنید.
- نسخهٔ PHP توصیه‌شده 8.5 است؛ `composer.json` حداقل `^8.3` را می‌پذیرد.
- کد پیاده‌سازی مرجع نهایی است؛ در صورت مشاهدهٔ اختلاف بین این README و کد، کد معتبر است و این سند به‌روزرسانی می‌شود.

## مجوز

این پروژه نرم‌افزار اختصاصی (`proprietary`) است و استفاده از آن تابع قرارداد با مالک سرویس است.

</div>
