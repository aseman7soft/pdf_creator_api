<div dir="rtl">

# راهنمای استقرار (Deployment)

راهنمای گام‌به‌گام استقرار سرویس روی **Ubuntu 22.04** با **Nginx**، **PHP 8.5-FPM**، **MySQL 8** و **Gotenberg 9** (از قبل نصب‌شده روی loopback پورت `3000`).

- دامنه: `https://pdf.as7th.com`
- مسیر پروژه: `/var/www/pdf-service`
- کاربر اجرا: `www-data`
- فهرست سایت Nginx: `/etc/nginx/sites-available/pdf.as7th.com.conf`
- PHP-FPM socket: `/run/php/php8.5-fpm.sock`

> ## ⚠️ هشدارهای مهم پیش از شروع
>
> 1. **Gotenberg از قبل روی سرور نصب است** و روی `127.0.0.1:3000` گوش می‌دهد. **آن را نصب مجدد نکنید، کانتینر/سرویس آن را ری‌استارت یا بازسازی نکنید، پورت آن را عمومی نکنید و به سایر سرویس‌های Docker دست نزنید.** در این سند فقط *بررسی سلامت* آن آمده است (خواندن وضعیت و لاگ، بدون تغییر).
> 2. **سایت‌های موجود Nginx را تغییر ندهید.** فقط فایل سایت جدید `pdf.as7th.com` را اضافه کنید. هرگز `default` یا کانفیگ سایت‌های دیگر را overwrite/حذف نکنید.
> 3. **`systemctl reload nginx`** استفاده کنید، نه `restart`، تا اتصال سایت‌های دیگر قطع نشود.
> 4. **`systemctl reload php8.5-fpm`** فقط در صورت نیاز؛ `restart` باعث قطع کوتاه همهٔ سایت‌های روی PHP 8.5 می‌شود. در ساعات کم‌بار انجام دهید.
> 5. **هیچ رمز/کلیدی را در git، این سند یا اسکریپت‌ها قرار ندهید.** مقادیر حساس فقط در `.env` سرور (با مجوز `640`) و secret manager نگهداری شوند.
> 6. پیش از هر `migrate`، از دیتابیس **بکاپ** بگیرید (بخش ۱۴).

## ۱) آماده‌سازی سرور

```bash
# اجرا با کاربر دارای sudo (کاربر deploy یا root)
sudo timedatectl set-timezone Asia/Tehran     # اختیاری؛ برای زمان‌بندی شمسی توصیه می‌شود
sudo apt update && sudo apt upgrade -y

# ابزارهای پایه
sudo apt install -y curl unzip zip git nginx mysql-client \
  software-properties-common ca-certificates lsb-release apt-transport-https

# PHP 8.5 از مخزن ondrej
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring \
  php8.5-xml php8.5-curl php8.5-zip php8.5-intl php8.5-bcmath \
  php8.5-gd php8.5-opcache

php -v            # باید PHP 8.5.x باشد
php -m | grep -E 'pdo_mysql|mbstring|xml|curl|intl|zip'
```

> اگر `php` به نسخهٔ قدیمی اشاره می‌کند، از مسیر صریح `/usr/bin/php8.5` استفاده کنید یا `update-alternatives` را تنظیم کنید. **نسخهٔ 8.5 برای این پروژه توصیه شده است** (`composer.json` حداقل `^8.3` را می‌پذیرد).

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version    # Composer 2.x
```

### Node.js (برای build assetها)

Vite 7 به Node 20.19+ یا 22.12+ نیاز دارد:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

> در صورت عدم امکان نصب Node روی سرور، می‌توانید `public/build` را روی ماشین build بسازید و با `rsync`/artifact منتقل کنید. هر دو روش در `deploy/deploy.sh` (با متغیر `SKIP_NPM`) پشتیبانی می‌شود.

## ۲) بررسی Gotenberg (بدون تغییر)

Gotenberg باید روی loopback بالا باشد. فقط این موارد را چک کنید:

```bash
# سلامت خود سرویس Gotenberg (باید 200 برگرداند)
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3000/health

# وضعیت کانتینر/sرویس (فقط مشاهده؛ هیچ تغییری ندهید)
sudo docker ps --filter 'publish=3000' || true
ss -lntp | grep 3000 || true
```

- اگر `curl` کد `200` نداد، **خودتان آن را ری‌استارت/نصب نکنید**؛ وضعیت را به مالک سرور/تیم زیرساخت اطلاع دهید. فقط لاگ‌ها را بخوانید (`sudo docker logs --tail=100 <container>` که نام کانتینر را از `docker ps` می‌گیرید).
- Gotenberg **نباید** از بیرون (اینترنت) قابل دسترسی باشد. بررسی کنید پورت `3000` فقط روی `127.0.0.1` باشد (خروجی `ss` باید `127.0.0.1:3000` نشان دهد، نه `0.0.0.0:3000`).
- در `.env` مقدار `GOTENBERG_URL=http://127.0.0.1:3000` قرار می‌گیرد. هرگز آدرس عمومی یا دارای اعتبارنامه نگذارید.

## ۳) دیتابیس MySQL 8

```bash
sudo mysql
```

```sql
CREATE DATABASE pdf_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pdf_service'@'127.0.0.1' IDENTIFIED BY '<STRONG_DB_PASSWORD>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON pdf_service.* TO 'pdf_service'@'127.0.0.1';
FLUSH PRIVILEGES;
```

- `<STRONG_DB_PASSWORD>` را از یک تولیدکنندهٔ رمز بسازید (حداقل ۲۴ کاراکتر تصادفی) و فقط در `.env` سرور نگه دارید.
- اگر MySQL روی socket محلی است `@'localhost'` بدهید؛ در غیر این صورت `@'127.0.0.1'`.
- در صورت نیاز به بکاپ با `mysqldump`، همان کاربر نیاز به `SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER` دارد (بخش ۱۴).
- `sql_mode` پیش‌فرض MySQL 8 مناسب است؛ کاراکترست `utf8mb4` الزامی است.

## ۴) دریافت کد و ساختار پوشه

```bash
sudo mkdir -p /var/www/pdf-service
sudo chown -R www-data:www-data /var/www/pdf-service
```

### روش A — git (پیشنهادی)

```bash
sudo -u www-data git clone <REPO_URL> /var/www/pdf-service
cd /var/www/pdf-service
sudo -u www-data git checkout <TAG_OR_COMMIT>
```

### روش B — rsync از ماشین build

```bash
rsync -az --delete \
  --exclude '.git' --exclude 'node_modules' --exclude '.env' \
  --exclude 'storage/app/private/documents/*' \
  ./ deploy@pdf.as7th.com:/var/www/pdf-service/
```

> **`.env` هرگز از مخزن/ماشین build منتقل یا commit نمی‌شود.** فقط روی سرور ساخته می‌شود (بخش ۶).
> `storage/app/private/documents` را در rsync دست نزنید تا اسناد موجود پاک نشوند.

## ۵) تنظیم PHP-FPM

فایل pool پیش‌فرض `/etc/php/8.5/fpm/pool.d/www.conf` را بررسی کنید (در صورت لزوم با sudoedit). مقادیر پیشنهادی:

```ini
; /etc/php/8.5/fpm/pool.d/www.conf
user = www-data
group = www-data
listen = /run/php/php8.5-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

; برای تولید PDFهای سنگین؛ هماهنگ با GOTENBERG_TIMEOUT و PDF_QUEUE_TIMEOUT
request_terminate_timeout = 300
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 8M
php_admin_value[post_max_size] = 10M
php_admin_value[expose_php] = Off
```

- `request_terminate_timeout` باید از `GOTENBERG_TIMEOUT` (پیش‌فرض 90) و `PDF_QUEUE_TIMEOUT` (پیش‌فرض 180) بیشتر باشد.
- `post_max_size`/`upload_max_filesize` باید از `PDF_MAX_UPLOAD_KB` (پیش‌فرض 5120KB) بیشتر باشند.
- در `/etc/php/8.5/fpm/php.ini` یا `conf.d`:
  ```ini
  opcache.enable=1
  opcache.memory_consumption=256
  opcache.validate_timestamps=0   ; در production؛ بعد از هر deploy، php-fpm را reload کنید
  ```
- اعمال کانفیگ:
  ```bash
  sudo php-fpm8.5 -t
  sudo systemctl reload php8.5-fpm
  ```
  > **توجه:** `restart` همهٔ سایت‌های روی PHP 8.5 را لحظه‌ای قطع می‌کند؛ `reload` امن‌تر است.

## ۶) ساخت `.env` تولید

```bash
cd /var/www/pdf-service
sudo -u www-data cp .env.example .env
sudo -u www-data chmod 640 .env
sudo -u www-data php artisan key:generate
```

سپس `.env` را با `sudoedit /var/www/pdf-service/.env` ویرایش کنید. می‌توانید از [../deploy/.env.production.example](../deploy/.env.production.example) الگو بگیرید. مقادیر الزامی:

```dotenv
APP_NAME="Central PDF Service"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:<GENERATED_BY_artisan_key:generate>   # با key:generate ساخته می‌شود؛ هرگز تغییرش ندهید
APP_URL=https://pdf.as7th.com
APP_TIMEZONE=Asia/Tehran

APP_LOCALE=fa
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pdf_service
DB_USERNAME=pdf_service
DB_PASSWORD=<STRONG_DB_PASSWORD>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=false
SESSION_LIFETIME=120

QUEUE_CONNECTION=database        # یا redis در صورت نصب Redis
CACHE_STORE=database             # یا redis
FILESYSTEM_DISK=local

GOTENBERG_URL=http://127.0.0.1:3000
GOTENBERG_CONNECT_TIMEOUT=10
GOTENBERG_TIMEOUT=90

PDF_DISK=local
PDF_DOCUMENT_PATH=documents
PDF_DOCUMENT_RETENTION_DAYS=30
PDF_MAX_PAYLOAD_KB=1024
PDF_SIGNED_URL_TTL=30

PDF_QUEUE_NAME=documents
PDF_QUEUE_TRIES=3
PDF_QUEUE_TIMEOUT=180
PDF_QUEUE_BACKOFF=15

API_RATE_LIMIT_PER_MINUTE=60
API_KEY_PREFIX=pdfsvc
API_IDEMPOTENCY_TTL_HOURS=24
API_KEY_HASH_SALT=<OUTPUT_OF_openssl_rand_hex_32>

ADMIN_EMAIL=admin@tarahsite.net
ADMIN_PASSWORD=                  # برای seed اولیه؛ یا خالی بگذارید تا رمز تصادفی چاپ شود
```

ساخت `API_KEY_HASH_SALT` (فقط **یک‌بار** در طول عمر نصب تولید شود):

```bash
openssl rand -hex 32
```

> ### 🔑 تثبیت `API_KEY_HASH_SALT`
> کلیدهای API با **HMAC-SHA256** و این salt ذخیره می‌شوند (`config/api.php` → `api.hash_salt`). اگر این مقدار تغییر کند، **همهٔ کلیدهای موجود بی‌اعتبار می‌شوند** و باید همه را دوباره صادر کنید. این مقدار را همراه با `APP_KEY` در بکاپ/secret manager نگه دارید و هرگز چرخش نکنید (مگر در حادثهٔ امنیتی، با برنامهٔ صدور مجدد همهٔ کلیدها).

> همچنین کلیدها به `APP_KEY`/`API_KEY_HASH_SALT` وابسته‌اند؛ `key:generate` مجدد روی نصب موجود ممنوع است (فقط نصب اولیه).

## ۷) نصب وابستگی‌ها، مهاجرت و seed

```bash
cd /var/www/pdf-service
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# بکاپ قبل از مهاجرت (بخش ۱۴ را ببینید)
sudo -u www-data php artisan migrate --force
```

### seed اولیه (فقط بار اول)

```bash
sudo -u www-data php artisan db:seed --force
```

- `SettingsSeeder` → تنظیمات پیش‌فرض
- `AdminUserSeeder` → کاربر مدیر (`ADMIN_EMAIL`، پیش‌فرض `admin@tarahsite.net`)؛ اگر `ADMIN_PASSWORD` خالی باشد رمز تصادفی **یک‌بار** در خروجی چاپ می‌شود — آن را فوراً در جای امن ذخیره و سپس رمز را از پنل تغییر دهید. اگر `ADMIN_PASSWORD` مقدار داشته باشد همان رمز تنظیم می‌شود (و در اجرای مجدد seed فقط وقتی رمز عوض شود، به‌روزرسانی می‌شود).
- `AssetSeeder` → فونت‌های Vazirmatn روی دیسک خصوصی
- `TemplateSeeder` → سه قالب نمونه (`hotel-voucher`, `invoice-standard`, `tour-itinerary`)
- `DemoApiProjectSeeder` → فقط در `local`/`testing`؛ در production پروژهٔ نمایشی ساخته **نمی‌شود**.

> ### ⚠️ seed کامل را دوباره در production اجرا نکنید
> `TemplateSeeder` قالب‌های نمونه را از روی slug به‌روزرسانی و **نسخه‌ها و متغیرهایشان را بازنویسی** می‌کند. در استقرارهای بعدی فقط:
> ```bash
> sudo -u www-data php artisan db:seed --class=SettingsSeeder --force   # در صورت افزودن تنظیم جدید
> ```
> و مدیریت قالب/کاربر را از پنل انجام دهید.

### ساخت assetهای فرانت‌اند

```bash
cd /var/www/pdf-service
sudo -u www-data npm ci
sudo -u www-data npm run build        # خروجی: public/build
```

- برای نصب Node به بخش ۱ رجوع کنید. اگر روی سرور Node ندارید، `public/build` را از CI/ماشین build منتقل کنید.
- **Fallback:** پنل به‌صورت شرطی عمل می‌کند: اگر `public/build/manifest.json` موجود باشد از assetهای Vite استفاده می‌شود؛ در غیر این صورت از فایل‌های از پیش موجود در `public/vendor/` (Bootstrap RTL، Bootstrap Icons، فونت Vazirmatn، `admin.css` و `panel.js`) استفاده می‌شود. بنابراین build «الزامی» نیست، اما برای assets به‌روز و کش‌بندی طولانی **توصیه می‌شود**. اگر `SKIP_NPM=1` می‌گذارید، از وجود `public/vendor/` مطمئن شوید (در مخزن موجود است).
- پس از build، مالکیت `public/build` باید `www-data` باشد.

### لینک storage

اگر پنل از دیسک عمومی (`storage/app/public`) برای پیش‌نمایش/دانلود asset استفاده می‌کند:

```bash
sudo -u www-data php artisan storage:link
```

> فایل‌های PDF **هرگز** از دیسک عمومی سرو نمی‌شوند؛ دانلود فقط از کنترلر API/پنل است.

## ۸) مجوزهای فایل و پوشه

```bash
cd /var/www/pdf-service

sudo chown -R www-data:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;

# پوشه‌های نوشتنی
sudo chmod -R 775 storage bootstrap/cache
sudo chgrp -R www-data storage bootstrap/cache

# .env فقط خواندنی برای مالک/گروه
sudo chown www-data:www-data .env
sudo chmod 640 .env

# اجرایی‌های لازم
sudo chmod +x artisan deploy/deploy.sh
```

- `storage/` و `bootstrap/cache/` باید برای `www-data` قابل نوشتن باشند؛ بدون این، کش/لاگ/نشست خطا می‌دهد.
- مالکیت کلی کد با `www-data` است تا `git pull`/`deploy.sh` و artisan بدون مشکل اجرا شوند. برای سخت‌گیری بیشتر می‌توان کد را `root:www-data` با `644/755` و فقط `storage`/`bootstrap/cache` را `www-data` کرد (در این صورت deploy باید با sudo اجرا شود).

## ۹) Nginx — فقط سایت جدید

فایل کانفیگ آمادهٔ سایت در [../deploy/nginx/pdf.as7th.com.conf](../deploy/nginx/pdf.as7th.com.conf) است.

```bash
sudo cp deploy/nginx/pdf.as7th.com.conf /etc/nginx/sites-available/pdf.as7th.com.conf

# فقط بررسی کنید که لینک سایت دیگری را تغییر نمی‌دهید:
ls -la /etc/nginx/sites-enabled/

sudo ln -sf /etc/nginx/sites-available/pdf.as7th.com.conf \
            /etc/nginx/sites-enabled/pdf.as7th.com.conf

sudo nginx -t
sudo systemctl reload nginx
```

> - **سایت‌های دیگر را تغییر ندهید.** فایل جدید مستقل است و فقط `pdf.as7th.com` را سرو می‌کند.
> - اگر فایل `sites-enabled/default` وجود دارد و با دامنهٔ جدید تداخل ندارد، آن را دست نزنید.
> - فایل کانفیگ شامل security headers، deny dotfiles، محدودیت حجم بدنه، timeoutهای FastCGI، کش استاتیک و لاگ است.
> - مسیر PHP-FPM در فایل کانفیگ `unix:/var/run/php/php8.5-fpm.sock` است؛ آن را با نسخهٔ نصب‌شده در سرور هماهنگ کنید.

## ۱۰) SSL با Certbot (یا دستی)

### روش A — Certbot موجود روی سرور

اگر certbot از قبل نصب است، **فقط برای دامنهٔ جدید** گواهی بگیرید و کانفیگ را خودکار نکنید تا سایت‌های دیگر تغییر نکنند:

```bash
# certbot با webroot (بدون تغییر خودکار کانفیگ Nginx)
sudo mkdir -p /var/www/letsencrypt
sudo certbot certonly --webroot -w /var/www/letsencrypt \
  -d pdf.as7th.com --agree-tos --email <ADMIN_EMAIL>
```

سپس در فایل کانفیگ Nginx مسیرهای گواهی را مطابق کامنت‌های فایل تنظیم کنید:

```nginx
ssl_certificate     /etc/letsencrypt/live/pdf.as7th.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/pdf.as7th.com/privkey.pem;
```

استقرار اولیه: قبل از گرفتن گواهی، بلوک HTTP فقط redirect دارد و `/var/www/letsencrypt` باید در بلوک HTTP `location /.well-known/acme-challenge/` داشته باشد (در فایل کانفیگ آماده است). ساده‌ترین مسیر برای اولین استقرار: ابتدا بلوک HTTP را فعال کنید، گواهی را بگیرید، سپس بلوک HTTPS را فعال و `nginx -t && systemctl reload nginx` بزنید.

### روش B — گواهی دستی/وجود گواهی روی سرور

مسیرهای `ssl_certificate` و `ssl_certificate_key` را به گواهی فعلی سرور تغییر دهید و بعد از تغییر:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### تمدید خودکار

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

> اگر certbot از طریق سایت‌های دیگر تنظیم شده است، تمدید آن سایت‌ها را مختل نکنید؛ فقط فایل سایت جدید را با همان گواهی‌ها هماهنگ کنید.

## ۱۱) Queue Worker با systemd

فایل سرویس آماده: [../deploy/systemd/pdf-queue.service](../deploy/systemd/pdf-queue.service)

```bash
sudo cp deploy/systemd/pdf-queue.service /etc/systemd/system/pdf-queue.service
sudo systemctl daemon-reload
sudo systemctl enable --now pdf-queue.service
sudo systemctl status pdf-queue.service

# مشاهدهٔ زندهٔ لاگ
sudo journalctl -u pdf-queue.service -f
```

نکات:

- سرویس با `User=www-data` و `WorkingDirectory=/var/www/pdf-service` اجرا می‌شود.
- `--queue=documents --tries=3 --timeout=180 --backoff=15` باید با `PDF_QUEUE_*` هماهنگ باشد (در فایل سرویس توضیح داده شده است).
- `Restart=always` و `RestartSec=5` برای بازیابی خودکار.
- بعد از هر deploy: `sudo systemctl restart pdf-queue.service` (یا `php artisan queue:restart`).
- برای افزایش ظرفیت، از یک سرویس دوم/چند instance استفاده کنید:
  ```bash
  sudo cp /etc/systemd/system/pdf-queue.service /etc/systemd/system/pdf-queue@.service
  # در فایل @، ExecStart را یکسان بگذارید و از template استفاده کنید:
  sudo systemctl enable --now pdf-queue@1.service pdf-queue@2.service
  ```
  (نمونهٔ template در همان فایل کامنت شده است.)

## ۱۲) Scheduler

> **الزامی:** زمان‌بندی‌های `routes/console.php` (پاک‌سازی روزانهٔ اسناد 03:30، `pdf:health` ساعتی، `queue:prune-failed` روزانه) فقط زمانی اجرا می‌شوند که `php artisan schedule:run` هر دقیقه اجرا شود. یکی از دو روش زیر را فعال کنید (نه هر دو).

### روش A — systemd timer (پیشنهادی)

```bash
sudo cp deploy/systemd/pdf-scheduler.service /etc/systemd/system/pdf-scheduler.service
sudo cp deploy/systemd/pdf-scheduler.timer   /etc/systemd/system/pdf-scheduler.timer

sudo systemctl daemon-reload
sudo systemctl enable --now pdf-scheduler.timer
sudo systemctl list-timers pdf-scheduler.timer

# بررسی taskهای زمان‌بندی‌شده
sudo -u www-data php artisan schedule:list
```

### روش B — cron

اگر systemd timer نمی‌خواهید، یک خط cron برای `www-data` اضافه کنید:

```bash
sudo crontab -u www-data -e
```

```cron
# Laravel scheduler — هر دقیقه
* * * * * cd /var/www/pdf-service && php artisan schedule:run >> /dev/null 2>&1
```

> از روش A یا B استفاده کنید، نه هر دو (اجرای دوبارهٔ schedule).

## ۱۳) کش و بهینه‌سازی production

```bash
cd /var/www/pdf-service
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache   # در صورت استفاده از event cache
sudo systemctl reload php8.5-fpm           # برای پاک شدن OPcache
```

- بعد از هر تغییر `.env` یا کد: `php artisan optimize:clear` سپس دوباره کش‌ها (یا `php artisan optimize`).
- در حالت `config:cache`، تغییر `.env` بدون پاک‌سازی کش اثر نمی‌کند. این نکته را در رانبوک [OPERATIONS.md](OPERATIONS.md) هم ببینید.

## ۱۴) Health Check و Smoke Test

```bash
# سلامت لایه‌های داخلی
curl -sS -o /dev/null -w '%{http_code}\n' https://pdf.as7th.com/up
curl -sS https://pdf.as7th.com/api/v1/health | head -c 500

# وضعیت سرویس‌ها
sudo systemctl is-active nginx php8.5-fpm pdf-queue.service pdf-scheduler.timer
sudo -u www-data php artisan queue:failed | head -20
```

- `/api/v1/health` وضعیت DB و Gotenberg را (بدون جزئیات حساس) برمی‌گرداند.
- تست end-to-end با Gotenberg واقعی:
  ```bash
  export PDF_BASE_URL="https://pdf.as7th.com"
  export PDF_API_KEY="<کلید واقعی؛ در فایل ذخیره نکنید>"
  ./scripts/integration-test.sh
  ```

## ۱۵) چرخش لاگ (Log Rotation)

Laravel با `LOG_STACK=daily` (پیش‌فرض `.env.example`) لاگ‌ها را روزانه می‌چرخاند و `LOG_DAILY_DAYS` (پیش‌فرض 14 روز) نگه می‌دارد. برای اطمینان بیشتر:

```bash
sudo tee /etc/logrotate.d/pdf-service >/dev/null <<'EOF'
/var/www/pdf-service/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    su www-data www-data
}
EOF
sudo logrotate -d /etc/logrotate.d/pdf-service   # تست (dry-run)
```

- `journalctl` سرویس‌های systemd به‌صورت خودکار مدیریت می‌شود:
  ```bash
  sudo journalctl --vacuum-time=30d
  ```

## ۱۶) استقرار بدون قطعی (Zero-downtime) و Rollback

### استقرار استاندارد (deploy.sh)

فایل [../deploy/deploy.sh](../deploy/deploy.sh) مراحل امن را انجام می‌دهد:

```bash
cd /var/www/pdf-service
sudo APP_DIR=/var/www/pdf-service DOMAIN=pdf.as7th.com ./deploy/deploy.sh
```

مراحل اسکریپت: نگهداری حالت maintenance (کوتاه)، `git pull`/rsync، `composer install --no-dev --optimize-autoloader`، `migrate --force`، کش config/route/view، `npm ci && npm run build`، مجوزها، `queue:restart`، سپس health check و خروج از maintenance. هیچ secret داخل اسکریپت نیست و از `.env` سرور استفاده می‌کند.

> برای **zero-downtime واقعی** از الگوی release + symlink استفاده کنید:
> ```text
> /var/www/pdf-service/
> ├── releases/2026-11-08T120000/    # نسخه‌های کامل
> ├── shared/.env                    # لینک شده در هر release
> ├── shared/storage -> ...          # storage مشترک
> └── current -> releases/2026-11-08T120000
> ```
> Nginx `root` را به `current/public` بدهید؛ سپس هر release جدید جداگانه build شود و در انتها symlink `current` به‌صورت atomic جابه‌جا شود. `storage` و `.env` در releaseها symlink به `shared` باشند. این روش در `deploy.sh` با متغیر `RELEASES=1` قابل فعال‌سازی است.

### Rollback

```bash
cd /var/www/pdf-service
sudo -u www-data git log --oneline -5
sudo -u www-data git checkout <PREVIOUS_COMMIT_OR_TAG>

sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force     # فقط اگر migration این نسخه وجود دارد
sudo -u www-data php artisan optimize:clear && sudo -u www-data php artisan optimize
sudo systemctl reload php8.5-fpm
sudo systemctl restart pdf-queue.service
```

> - **بازگردانی migration خودکار نیست.** اگر نسخهٔ جدید migration ناسازگار داشته باشد، ابتدا از بکاپ دیتابیس restore کنید (بخش ۱۷) یا `php artisan migrate:rollback --step=1` را فقط با بررسی دقیق اجرا کنید.
> - قبل از rollback مطمئن شوید `API_KEY_HASH_SALT`/`APP_KEY` تغییر نکرده است.
> - فایل‌های PDF قدیمی در storage دست‌نخورده می‌مانند.

## ۱۷) پشتیبان‌گیری و بازیابی

### دیتابیس

```bash
# بکاپ
sudo mkdir -p /var/backups/pdf-service
sudo mysqldump --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 \
  -u pdf_service -p pdf_service \
  | gzip > /var/backups/pdf-service/db-$(date +%F-%H%M%S).sql.gz

# بازیابی
gunzip -c /var/backups/pdf-service/db-YYYY-MM-DD-HHMMSS.sql.gz \
  | sudo mysql -u pdf_service -p pdf_service
```

### فایل‌ها (اسناد تولیدشده + assets)

```bash
sudo tar -czf /var/backups/pdf-service/storage-$(date +%F).tar.gz \
  -C /var/www/pdf-service storage/app/private storage/logs

# بازیابی
sudo tar -xzf /var/backups/pdf-service/storage-YYYY-MM-DD.tar.gz \
  -C /var/www/pdf-service
sudo chown -R www-data:www-data /var/www/pdf-service/storage
```

### تنظیم بکاپ خودکار (cron — فقط برای این پروژه)

```bash
sudo crontab -e
```

```cron
# بکاپ روزانهٔ دیتابیس و storage — ساعت ۳:۱۵ بامداد
15 3 * * * root /usr/local/bin/pdf-service-backup.sh >> /var/log/pdf-service-backup.log 2>&1
```

نمونهٔ `pdf-service-backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
BACKUP_DIR=/var/backups/pdf-service
mkdir -p "$BACKUP_DIR"
STAMP=$(date +%F-%H%M%S)
mysqldump --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 \
  -u pdf_service -p"$DB_PASSWORD" pdf_service | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"
tar -czf "$BACKUP_DIR/storage-$STAMP.tar.gz" \
  -C /var/www/pdf-service storage/app/private
# نگهداری ۱۴ روز
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +14 -delete
find "$BACKUP_DIR" -name 'storage-*.tar.gz' -mtime +14 -delete
```

> رمز دیتابیس را داخل اسکریپت نگذارید؛ از `/root/.my.cnf` با مجوز `600` یا `EnvironmentFile` سیستم‌دم استفاده کنید. بکاپ‌ها را رمزنگاری‌شده (مثلاً `gpg`) به مقصد خارج از سرور منتقل کنید.
> **`API_KEY_HASH_SALT` و `APP_KEY` بخشی از بکاپ امنیتی هستند**؛ بدون آن‌ها کلیدها/داده‌های رمزنگاری‌شده قابل بازیابی نیستند؛ آن‌ها را در secret manager نگه دارید.

## ۱۸) سخت‌سازی امنیتی سرور

```bash
# فایروال: فقط SSH/HTTP/HTTPS
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status verbose

# پورت Gotenberg نباید عمومی باشد
sudo ss -lntp | grep 3000     # باید 127.0.0.1:3000 باشد، نه 0.0.0.0
```

- SSH: فقط کلید، غیرفعال‌کردن `PasswordAuthentication` و `PermitRootLogin no` (در صورت امکان).
- `fail2ban` برای SSH/Nginx.
- `expose_php=Off`، `server_tokens off` در Nginx، حذف `X-Powered-By`.
- `.env` با مجوز `640` و مالک `www-data`؛ هیچ `.env` در web root قابل دسترسی نیست (Nginx dotfiles را deny می‌کند).
- کاربر دیتابیس حداقل دسترسی؛ کاربر `root` MySQL فقط از localhost.
- به‌روزرسانی منظم: `apt upgrade`، `composer update` (با تست)، `npm audit`.
- رمز مدیر: قوی و یکتا؛ رمز پیش‌فرض seed را فوراً تغییر دهید.
- کلیدهای API: هر پروژه کلید جدا؛ برای هر نشت، revoke + rotate از پنل.
- محدودیت نرخ در Nginx (اختیاری و اضافی) برای `/api/`:
  ```nginx
  limit_req_zone $binary_remote_addr zone=pdf_api:10m rate=120r/m;
  ```
  (در فایل کانفیگ سایت به‌صورت کامنت آمده؛ فعال‌سازی نیاز به `http` context دارد — فقط در صورت نیاز و با احتیاط.)
- بکاپ‌ها رمزنگاری‌شده و خارج از سرور؛ تست بازیابی حداقل ماهی یک‌بار.
- مانیتورینگ لاگ ورود ناموفق پنل و 429/401 زیاد در `api_request_logs`.

جزئیات تهدیدها و کنترل‌ها: [SECURITY.md](SECURITY.md).

## ۱۹) چک‌لیست نهایی استقرار

- [ ] PHP 8.5-FPM فعال و `php -v` درست است
- [ ] MySQL 8 دیتابیس/کاربر ساخته شد
- [ ] Gotenberg روی `127.0.0.1:3000` سالم است (**بدون تغییر/ری‌استارت**)
- [ ] `.env` ساخته شد؛ `APP_KEY` و `API_KEY_HASH_SALT` ثابت‌اند؛ `.env` مجوز 640
- [ ] `composer install --no-dev` و `migrate --force` موفق
- [ ] seed اولیه اجرا شد؛ رمز مدیر ذخیره شد؛ کلید API از پنل ساخته شد
- [ ] `npm ci && npm run build` و `public/build` موجود
- [ ] مجوزهای `storage`/`bootstrap/cache` برای `www-data`
- [ ] Nginx فقط برای سایت جدید، `nginx -t` موفق و `reload` (نه restart)
- [ ] SSL فعال و redirect HTTP→HTTPS
- [ ] `pdf-queue.service` فعال (`systemctl enable --now`)
- [ ] `pdf-scheduler.timer` (یا cron) فعال
- [ ] `/up` و `/api/v1/health` سالم؛ `scripts/integration-test.sh` موفق
- [ ] بکاپ دیتابیس/فایل‌ها و logrotate تنظیم شد
- [ ] سایت‌های دیگر و سایر کانتینرها دست‌نخورده‌اند

</div>
