<div dir="rtl">

# رانبوک عملیات (Operations Runbook)

این سند رویه‌های روزمرهٔ نگهداری، پایش و رفع اشکال سرویس تولید اسناد را برای تیم عملیات/DevOps توضیح می‌دهد. مسیر تولید: `/var/www/pdf-service`، دامنه: `https://pdf.as7th.com`.

> دستورهای artisan را در production با کاربر `www-data` و از ریشهٔ پروژه اجرا کنید:
> ```bash
> cd /var/www/pdf-service
> sudo -u www-data php artisan <command>
> ```

## ۱) نقاط بررسی سلامت (Health)

| نقطه | نوع | کاربرد |
|---|---|---|
| `GET /up` | Laravel health route | بررسی زنده‌بودن PHP/اپ |
| `GET /health` | JSON سادهٔ سرویس | پاسخ سریع برای load balancer |
| `GET /api/v1/health` | عمومی، کش ۶۰ ثانیه | `status` = `ok`/`degraded` + `checks.database`/`checks.gotenberg` (بدون جزئیات حساس) |
| `GET http://127.0.0.1:3000/health` | Gotenberg | سلامت موتور رندر (فقط از سرور) |
| `php artisan pdf:health [--json]` | CLI | بررسی DB + storage (نوشتن فایل آزمایشی) + Gotenberg + خلاصهٔ صف؛ `exit != 0` در صورت خرابی |

```bash
# از داخل سرور
curl -fsS -o /dev/null -w '%{http_code}\n' http://127.0.0.1/up
curl -fsS http://127.0.0.1/api/v1/health
curl -fsS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3000/health
sudo -u www-data php artisan pdf:health --json

# از بیرون
curl -fsS -o /dev/null -w '%{http_code}\n' https://pdf.as7th.com/up
curl -fsS https://pdf.as7th.com/api/v1/health
```

- پاسخ سالم `api/v1/health`: `success: true` و `data.status = "ok"`؛ اگر DB یا Gotenberg خطا بدهد `status = "degraded"` و `checks.gotenberg = "unavailable"` می‌شود (پاسخ HTTP همچنان 200 است تا مانیتورینگ بتواند جزئیات را ببیند). برای هشدار، `status != ok` یا `checks.gotenberg != ok` را ملاک بگذارید.
- `pdf:health` مقداری از storage/file probe استفاده می‌کند؛ اگر `exit code` غیرصفر شد یعنی DB/storage/Gotenberg مشکل دارد و نباید deploy را موفق تلقی کرد.
- مانیتورینگ بیرونی: درخواست دوره‌ای (۱ دقیقه) به `/up` + `/api/v1/health` با هشدار در صورت non-200 یا `status != ok`.

## ۲) وضعیت سرویس‌ها

```bash
sudo systemctl status nginx php8.5-fpm pdf-queue.service pdf-scheduler.timer
sudo systemctl is-active nginx php8.5-fpm pdf-queue.service pdf-scheduler.timer
sudo systemctl list-timers | grep -E 'pdf|php|nginx'
```

| سرویس | نقش | دستور |
|---|---|---|
| `nginx` | وب/پروکسی | `sudo systemctl reload nginx` |
| `php8.5-fpm` | PHP | `sudo systemctl reload php8.5-fpm` |
| `pdf-queue.service` | worker صف | `sudo systemctl restart pdf-queue.service` |
| `pdf-scheduler.timer` | اجرای scheduler | `sudo systemctl status pdf-scheduler.timer` |
| `mysql` | دیتابیس | `sudo systemctl status mysql` |
| Gotenberg (Docker) | موتور PDF | فقط مشاهده: `sudo docker ps` / `sudo docker logs --tail=100 <container>` — **تغییر ندهید** |

## ۳) پایش صف و retry

### مشاهده

- پنل: `/admin/queue` — لیست `document_jobs` و `failed_jobs` با attempts/duration/error و دکمه‌های retry/delete.
- پایگاه داده:
  ```sql
  -- شمارش وضعیت صف اسناد در ۲۴ ساعت گذشته
  SELECT status, COUNT(*) FROM document_jobs
  WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY status;

  -- jobهای گیرکرده (processing طولانی)
  SELECT id, generated_document_id, attempts, started_at
  FROM document_jobs
  WHERE status = 'processing' AND started_at < NOW() - INTERVAL 15 MINUTE;

  -- اسناد pending قدیمی
  SELECT id, ulid, status, created_at FROM generated_documents
  WHERE status IN ('pending','processing') AND created_at < NOW() - INTERVAL 30 MINUTE;
  ```

### CLI

```bash
sudo -u www-data php artisan queue:failed
sudo -u www-data php artisan queue:retry all
sudo -u www-data php artisan queue:retry <id>
sudo -u www-data php artisan queue:forget <id>
sudo -u www-data php artisan queue:flush        # ⚠️ حذف همهٔ failed jobs
# پایش طول صف (فرمت: connection:queue)
sudo -u www-data php artisan queue:monitor database:documents
```

### retry از سرویس

- retry دستی سند ناموفق از پنل: `/admin/documents/{id}` → «تلاش مجدد» (فقط status=failed؛ سرویس `DocumentGenerationService::retry()`).
- retry خودکار: `PDF_QUEUE_TRIES` (پیش‌فرض 3) و `PDF_QUEUE_BACKOFF` (پیش‌فرض 15 ثانیه).
- **خطاهای غیرقابل retry** (`TEMPLATE_NOT_FOUND`, `TEMPLATE_INACTIVE`, `VALIDATION_ERROR`, `RENDER_ERROR`) در همان تلاش اول نهایی (`failed`) می‌شوند؛ خطاهای `GOTENBERG_*`/`STORAGE_ERROR` تا اتمام `tries` با backoff retry می‌شوند. پس از اتمام تلاش‌ها باید بررسی ریشه‌ای شود.
- `ShouldBeUnique` با `uniqueId = pdf-document:{id}` و `uniqueFor=3600` از اجرای هم‌زمان/تکراری همان سند جلوگیری می‌کند.
- پس از هر بار تغییر کد/کانفیگ worker: `sudo systemctl restart pdf-queue.service` یا `php artisan queue:restart`.

### علت‌یابی رایج صف

| علامت | علت احتمالی | اقدام |
|---|---|---|
| jobها `pending` می‌مانند | worker خاموش/کرش کرده | `systemctl status pdf-queue.service`, `journalctl -u pdf-queue.service` |
| `processing` گیرکرده | timeout/Gotenberg کند | بررسی `journalctl`، `GOTENBERG_TIMEOUT`، اندازهٔ سند |
| `should be unique` | job تکراری برای همان سند | طبیعی؛ `ShouldBeUnique` جلوی اجرای هم‌زمان را می‌گیرد |
| `document_jobs` بدون job واقعی | dispatch شکست خورده | لاگ `storage/logs/laravel.log`، اتصال صف |
| نبود worker جدید پس از deploy | worker قدیمی کد قبلی را دارد | `queue:restart` / restart سرویس |

## ۴) نگهداری و پاک‌سازی اسناد منقضی

- مدت نگهداری: `PDF_DOCUMENT_RETENTION_DAYS` (پیش‌فرض 30 روز) و تنظیم `documents.retention_days`؛ `expires_at` هر سند هنگام ساخت با همین مقدار ست می‌شود.
- سند با `expires_at` گذشته: `GeneratedDocument::isExpired()` و `canDownload()=false`؛ فایل باید توسط دستور نگهداری حذف شود.
- دستور پاک‌سازی:
  ```bash
  sudo -u www-data php artisan documents:prune --dry-run   # پیش‌نمایش (بدون حذف)
  sudo -u www-data php artisan documents:prune             # حذف واقعی
  sudo -u www-data php artisan documents:prune --days=14   # override مدت نگهداری
  ```
  - اسناد منقضی (`expires_at <= now`) یا قدیمی‌تر از مدت نگهداری (بر اساس `generated_at`) انتخاب می‌شوند؛ فایل PDF حذف و رکورد soft delete می‌شود (قابل audit). اسناد در وضعیت `processing` رد می‌شوند.
- **زمان‌بندی (در `routes/console.php`):**
  | زمان | دستور | کار |
  |---|---|---|
  | روزانه 03:30 | `documents:prune` | حذف اسناد/فایل‌های منقضی (withoutOverlapping + onOneServer) |
  | ساعتی (دقیقهٔ ۰) | `pdf:health --json` | snapshot سلامت DB/storage/Gotenberg/صف |
  | روزانه 00:00 | `queue:prune-failed --hours=168` | حذف رکوردهای `failed_jobs` قدیمی‌تر از ۷ روز |
  ```bash
  sudo -u www-data php artisan schedule:list   # نمایش زمان‌بندی‌ها
  ```
  اجرای این زمان‌بندی نیازمند اجرای `php artisan schedule:run` هر دقیقه است (systemd timer `pdf-scheduler.timer` یا cron؛ [DEPLOYMENT.md](DEPLOYMENT.md) بخش ۱۲). بدون آن هیچ task ای اجرا نمی‌شود.
- پس از پاک‌سازی، رکوردها به‌صورت soft delete می‌مانند و فایل فیزیکی حذف می‌شود.

### بررسی فضای storage

```bash
du -sh /var/www/pdf-service/storage/app/private/documents
find /var/www/pdf-service/storage/app/private/documents -type f -name '*.pdf' | wc -l
```

> حذف دستی فایل‌ها را انجام ندهید مگر با هماهنگی؛ رکوردهای DB باید هم‌زمان به‌روزرسانی شوند.

## ۵) لاگ‌ها

| لاگ | مسیر | کاربرد |
|---|---|---|
| لاگ اپ | `/var/www/pdf-service/storage/logs/laravel.log` (یا `laravel-YYYY-MM-DD.log`) | خطاها، استثناها |
| worker صف | `sudo journalctl -u pdf-queue.service -f` | اجرای jobها/timeout |
| scheduler | `sudo journalctl -u pdf-scheduler.service` | اجرای زمان‌بندی |
| Nginx access/error | `/var/log/nginx/pdf.as7th.com.*.log` | وضعیت HTTP/خطاهای لبه |
| PHP-FPM | `/var/log/php8.5-fpm.log` (بسته به توزیع) | خطاهای PHP/children |
| MySQL slow/error | `/var/log/mysql/error.log` | کندی/خطای دیتابیس |
| لاگ درخواست API | جدول `api_request_logs` (پنل: `/admin/logs`) | مصرف، خطاها، زمان پاسخ |
| Gotenberg | `sudo docker logs --tail=200 <container>` | timeout/حافظه موتور (فقط خواندن) |

```bash
sudo tail -n 200 /var/www/pdf-service/storage/logs/laravel.log
sudo journalctl -u pdf-queue.service --since '1 hour ago' --no-pager
sudo tail -n 100 /var/log/nginx/pdf.as7th.com.error.log
```

- `LOG_STACK=daily` + logrotate (بخش ۱۵ DEPLOYMENT) — برای پاک‌سازی دستی:
  ```bash
  sudo find /var/www/pdf-service/storage/logs -name '*.log' -mtime +30 -delete
  sudo journalctl --vacuum-time=30d
  ```
- **قبل از ارسال لاگ‌ها به پشتیبانی، دادهٔ حساس (کلید/Authorization/بدنه) را redact کنید.**

## ۶) پایگاه داده: پایش و نگهداری

```sql
-- حجم جداول اصلی
SELECT table_name,
       ROUND((data_length + index_length) / 1024 / 1024, 1) AS size_mb,
       table_rows
FROM information_schema.tables
WHERE table_schema = 'pdf_service'
ORDER BY (data_length + index_length) DESC;

-- اسناد تولیدشده در ۷ روز گذشته
SELECT DATE(created_at) AS day, status, COUNT(*) AS cnt
FROM generated_documents
WHERE created_at >= NOW() - INTERVAL 7 DAY
GROUP BY day, status ORDER BY day DESC;

-- میانگین زمان تولید (موفق)
SELECT ROUND(AVG(duration_ms)) AS avg_ms, MAX(duration_ms) AS max_ms
FROM document_jobs
WHERE status = 'completed' AND created_at >= NOW() - INTERVAL 7 DAY;

-- پروژه‌های پرمصرف ماه جاری
SELECT api_project_id, COUNT(*) AS requests
FROM api_request_logs
WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
GROUP BY api_project_id ORDER BY requests DESC;
```

- ایندکس‌های موجود در migrationها برای `status/created_at` و `project/created_at` بهینه‌اند؛ قبل از افزودن گزارش‌های سنگین، `EXPLAIN` بگیرید.
- `api_request_logs` رشد زیادی دارد؛ در صورت نیاز آرشیو/پاک‌سازی دوره‌ای (مثلاً > 90 روز) با هماهنگی.
- بازبینی دوره‌ای: integrity، حجم، کندی کوئری‌ها؛ `SHOW FULL PROCESSLIST` هنگام کندی.

## ۷) پشتیبان‌گیری و بازیابی

روال کامل در [DEPLOYMENT.md](DEPLOYMENT.md) بخش ۱۷ آمده است. خلاصهٔ رانبوک:

```bash
# بکاپ دستی فوری
STAMP=$(date +%F-%H%M%S)
sudo mysqldump --single-transaction --routines --triggers --events \
  -u pdf_service -p pdf_service | gzip > /var/backups/pdf-service/db-$STAMP.sql.gz
sudo tar -czf /var/backups/pdf-service/storage-$STAMP.tar.gz \
  -C /var/www/pdf-service storage/app/private

# بررسی سالم بودن بکاپ
gunzip -t /var/backups/pdf-service/db-$STAMP.sql.gz && echo OK
```

- **پیش از هر migrate/upgrade بکاپ بگیرید.**
- بازیابی را حداقل ماهی یک‌بار روی محیط staging تمرین کنید.
- بدون `APP_KEY` و `API_KEY_HASH_SALT`، بازیابی payloadهای رمزنگاری‌شده/کلیدها کامل نیست؛ این مقادیر را جداگانه در secret manager نگه دارید.

## ۸) عیب‌یابی Gotenberg

> **قانون طلایی: Gotenberg سرویس مشترک/از پیش نصب‌شده است. آن را ری‌استارت/بازسازی/نصب مجدد نکنید و تنظیمات سایر کانتینرها را تغییر ندهید.** فقط مشاهده و هماهنگی با تیم زیرساخت.

### بررسی سلامت

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3000/health
curl -sS http://127.0.0.1:3000/health
sudo ss -lntp | grep 3000        # باید 127.0.0.1:3000 باشد
```

### علائم و اقدام

| علامت در سرویس | معنا | اقدام |
|---|---|---|
| `GOTENBERG_UNAVAILABLE` | اتصال برقرار نشد | بررسی بالا بودن Gotenberg + `GOTENBERG_URL`؛ تماس با تیم زیرساخت |
| `GOTENBERG_TIMEOUT` | رندر بیش از `GOTENBERG_TIMEOUT` | بررسی اندازه/پیچیدگی سند، حافظهٔ Chromium، افزایش timeout (فقط پس از بررسی) |
| `GOTENBERG_ERROR` (5xx) | خطای موتور/ورودی | لاگ Gotenberg را بخوانید؛ سند/asset معیوب را پیدا کنید |
| کندی و 502/504 از Nginx | صف/رندر اشباع | تعداد worker را کم/زیاد کنید، منابع را بررسی کنید |
| رندر ناقص فونت فارسی | asset فونت موجود نیست | `AssetSeeder`/فایل asset و `asset://vazirmatn-*` را بررسی کنید |
| `RENDER_ERROR` | خطای قالب/داده قبل از Gotenberg | خطای دقیق را در `storage/logs` ببینید؛ قالب را در پنل preview کنید |

### منابع

- Chromium حافظه‌بر است؛ چند worker هم‌زمان = چند رندر هم‌زمان. برای بار بالا workerها را با ظرفیت Gotenberg (CPU/RAM) هماهنگ کنید.
- در صورت نیاز به افزایش ظرفیت، Gotenberg را روی میزبان جدا (همچنان در شبکهٔ خصوصی) اجرا کنید و `GOTENBERG_URL` را به آن تغییر دهید — با هماهنگی تیم زیرساخت.
- لاگ/متریک Gotenberg فقط خواندنی: `sudo docker stats --no-stream`, `sudo docker logs --tail=200 <container>`.

## ۹) چرخش کلیدهای API

1. pنل → «پروژه‌ها» → پروژه → «کلیدها».
2. «چرخش» روی کلید فعال: کلید قبلی revoke و کلید جدید **یک‌بار** نمایش داده می‌شود.
3. کلید جدید را به مصرف‌کننده منتقل کنید و کلید قدیمی را از سمت او حذف کنید.
4. بررسی کنید `last_used_at` کلید قدیمی متوقف شده باشد (`/admin/logs` یا DB).
5. برای کلید لو رفته: فوراً revoke + rotate؛ بخش حادثه در [SECURITY.md](SECURITY.md).

> هرگز `API_KEY_HASH_SALT` را برای چرخش تغییر ندهید؛ این کار همهٔ کلیدها را باطل می‌کند. چرخش در سطح خود کلیدها انجام می‌شود.

## ۱۰) مقیاس‌دادن workerها

```bash
# مشاهدهٔ تعداد worker
systemctl list-units 'pdf-queue*'
ps aux | grep 'queue:work' | grep -v grep
```

- روش A: چند instance از سرویس (`pdf-queue@1`, `pdf-queue@2`؛ نمونهٔ template در فایل systemd).
- روش B: `systemd` با `ExecStart` چند worker یا `supervisor` (در صورت وجود).
- روش C: افزایش `pm.max_children` PHP-FPM فقط اگر درخواست‌های HTTP گلوگاه‌اند (نه صف).
- هماهنگ‌سازی: تعداد رندر هم‌زمان نباید از ظرفیت Gotenberg (CPU/RAM) بیشتر شود؛ در غیر این صورت timeout/5xx.
- برای صف بزرگ، `QUEUE_CONNECTION=redis` و `CACHE_STORE=redis` را در نظر بگیرید (نیازمند نصب/تنظیم Redis؛ با هماهنگی زیرساخت).
- مانیتورینگ: طول صف (`queue:monitor`)، نرخ failed، `duration_ms` و زمان انتظار.

## ۱۱) متریک‌ها و داشبورد

- پنل `/admin`: کل اسناد، اسناد امروز، درخواست‌ها، موفق/ناموفق، پروژه/قالب فعال، میانگین زمان تولید.
- DB (نمونه‌های کوئری در بخش ۶)؛ می‌توانید به Grafana/Prometheus یا ابزار موجود سازمان متصل کنید.
- متریک‌های پیشنهادی برای هشدار:
  - نرخ 5xx/429/401 (از `api_request_logs` و Nginx)
  - طول صف `documents` و تعداد `failed_jobs`
  - `document_jobs.duration_ms` (درصد 95)
  - سلامت Gotenberg (`/health`) و تأخیر پاسخ
  - فضای دیسک `storage` و حجم دیتابیس
  - latency `/up`
- لاگ‌های ساخت‌یافته: `request_id` در پاسخ `X-Request-Id` برای همبستگی بین API log و laravel.log.

## ۱۲) رویهٔ ارتقا (Upgrade)

1. **بکاپ** دیتابیس + `storage/app/private` + `.env` (بخش ۷).
2. بررسی تغییرات: `git log`, changelog، وابستگی‌های جدید composer/npm.
3. حالت maintenance کوتاه (در صورت نیاز): `php artisan down --retry=60`.
4. کد جدید: `git fetch && git checkout <TAG>` (یا rsync).
5. `composer install --no-dev --optimize-autoloader`
6. `php artisan migrate --force` (تمدید؛ rollback برنامه‌ریزی‌شده)
7. `npm ci && npm run build`
8. `php artisan optimize:clear && php artisan optimize` (config/route/view cache)
9. مجوزها: `chown -R www-data:www-data . && chmod -R ug+rwX storage bootstrap/cache`
10. `sudo systemctl reload php8.5-fpm` (نه restart) و `sudo systemctl restart pdf-queue.service`
11. `php artisan up`
12. Health + integration test: `/up`, `/api/v1/health`، `scripts/integration-test.sh`
13. پایش ۳۰ دقیقهٔ اول: خطاها، صف، Gotenberg.

> پس از هر ارتقا که `.env`/config را تغییر می‌دهد، **config cache را پاک/بازسازی کنید** (وگرنه مقادیر قدیمی استفاده می‌شوند).

## ۱۳) رویهٔ rollback

1. `git checkout <PREVIOUS_TAG_OR_COMMIT>` (یا symlink release قبلی).
2. `composer install --no-dev --optimize-autoloader` و `npm ci && npm run build`.
3. اگر migration سازگار نیست: **restore از بکاپ دیتابیس** (بخش ۷) — `migrate:rollback` فقط با بررسی دقیق.
4. `php artisan optimize:clear && php artisan optimize`؛ `systemctl reload php8.5-fpm`؛ `systemctl restart pdf-queue.service`.
5. Health check و smoke test. اسناد قدیمی در storage دست‌نخورده می‌مانند.

## ۱۴) کارهای دوره‌ای (Checklist)

| دوره | کار |
|---|---|
| روزانه | بررسی `/up` و `/api/v1/health`، failed jobs، فضای دیسک، خطای 5xx |
| هفتگی | بازبینی لاگ‌ها/`api_request_logs`، نرخ 429/401، صف، مدت رندر |
| ماهانه | تست بازیابی بکاپ، `composer audit`/`npm audit`، بازبینی `failed_jobs` قدیمی، بازبینی دسترسی کاربران پنل |
| فصلی | ارتقای نسخه‌های PHP/Node/MySQL/Gotenberg (با هماهنگی)، بازبینی امنیتی، بازبینی ظرفیت و scaling |
| هر deploy | بکاپ، تست، health check، `queue:restart`، پایش اولیه |

## ۱۵) دستورهای سریع مرجع

```bash
cd /var/www/pdf-service

# وضعیت کلی
sudo systemctl status nginx php8.5-fpm pdf-queue.service pdf-scheduler.timer
sudo -u www-data php artisan about
sudo -u www-data php artisan pdf:health

# صف
sudo -u www-data php artisan queue:failed
sudo -u www-data php artisan queue:retry all
sudo -u www-data php artisan queue:restart
sudo journalctl -u pdf-queue.service -f

# نگهداری
sudo -u www-data php artisan documents:prune --dry-run
sudo -u www-data php artisan schedule:list

# کش
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# لاگ
sudo tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/pdf.as7th.com.error.log
```

</div>
