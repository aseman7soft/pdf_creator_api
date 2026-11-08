#!/usr/bin/env bash
# =============================================================================
# Central PDF Service — safe production deploy helper
# =============================================================================
#
# استفاده:
#   cd /var/www/pdf-service
#   sudo APP_DIR=/var/www/pdf-service DOMAIN=pdf.as7th.com ./deploy/deploy.sh
#
# این اسکریپت: maintenance کوتاه → دریافت کد → composer → migrate → cache →
#   build assets → مجوزها → restart queue → reload php-fpm → health check.
#
# هیچ رمز/کلیدی در این اسکریپت نیست؛ همهٔ مقادیر حساس از `.env` سرور خوانده
# می‌شوند. اسکریپت هیچ سرویس دیگری (از جمله Gotenberg/سایر کانتینرها و
# سایت‌های دیگر Nginx) را تغییر نمی‌دهد.
#
# متغیرهای محیطی اختیاری:
#   APP_DIR            مسیر پروژه (پیش‌فرض: /var/www/pdf-service)
#   DOMAIN             دامنه برای health check (پیش‌فرض: pdf.as7th.com)
#   PHP_BIN            مسیر php (پیش‌فرض: auto-detect php8.5)
#   SKIP_PULL=1        دریافت کد/rsync را رد کن
#   SKIP_MIGRATE=1     migrate را رد کن
#   SKIP_NPM=1         build فرانت‌اند را رد کن (مثلاً build در CI)
#   SKIP_MAINTENANCE=1 بدون maintenance mode
#   RSYNC_SOURCE       در صورت نبود git، rsync از این مسیر
#   HEALTH_RETRIES     تعداد تلاش health check (پیش‌فرض: 10)
#   QUEUE_UNIT         نام سرویس systemd worker (پیش‌فرض: pdf-queue.service)
#   FPM_SERVICE        نام سرویس php-fpm (پیش‌فرض: php8.5-fpm)
# =============================================================================

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/pdf-service}"
DOMAIN="${DOMAIN:-pdf.as7th.com}"
PHP_BIN="${PHP_BIN:-}"
SKIP_PULL="${SKIP_PULL:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"
SKIP_NPM="${SKIP_NPM:-0}"
SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-0}"
RSYNC_SOURCE="${RSYNC_SOURCE:-}"
HEALTH_RETRIES="${HEALTH_RETRIES:-10}"
QUEUE_UNIT="${QUEUE_UNIT:-pdf-queue.service}"
FPM_SERVICE="${FPM_SERVICE:-php8.5-fpm}"
START_TS="$(date +%s)"
MAINTENANCE_ACTIVE=0
IS_ROOT=0
[[ "${EUID}" -eq 0 ]] && IS_ROOT=1

if [[ "${IS_ROOT}" -ne 1 ]]; then
    printf '\033[1;33m[warn]\033[0m اجرا بدون root: مراحل chown/systemctl (restart queue/reload php-fpm) رد می‌شوند.\n' >&2
    printf '\033[1;33m[warn]\033[0m اجرای پیشنهادی: sudo APP_DIR=%s ./deploy/deploy.sh\n' "${APP_DIR:-/var/www/pdf-service}" >&2
fi

# -----------------------------------------------------------------------------
# Logging / helpers
# -----------------------------------------------------------------------------
log()  { printf '\033[1;34m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*"; }
ok()   { printf '\033[1;32m[ ok ]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[warn]\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m[fail]\033[0m %s\n' "$*" >&2; }

on_error() {
    local exit_code=$? line=$1
    fail "خطا در خط ${line} (exit=${exit_code})"
    if [[ "${MAINTENANCE_ACTIVE}" -eq 1 ]]; then
        warn "خروج از maintenance mode برای بازگرداندن سرویس..."
        run_artisan up || true
    fi
    warn "سرویس در وضعیت نامشخص است. لاگ‌ها را بررسی کنید و در صورت نیاز rollback کنید:"
    warn "  git log --oneline -5"
    warn "  git checkout <PREVIOUS_TAG> && ./deploy/deploy.sh"
    warn "  راهنما: docs/OPERATIONS.md بخش ۱۳ (Rollback)"
    exit "${exit_code}"
}
trap 'on_error ${LINENO}' ERR

run_artisan() {
    if [[ "${IS_ROOT}" -eq 1 ]] && id -u www-data >/dev/null 2>&1; then
        sudo -u www-data -- "${PHP_BIN}" artisan "$@"
    else
        "${PHP_BIN}" artisan "$@"
    fi
}

run_npm() {
    if [[ "${IS_ROOT}" -eq 1 ]] && id -u www-data >/dev/null 2>&1; then
        sudo -u www-data -- npm "$@"
    else
        npm "$@"
    fi
}

have() { command -v "$1" >/dev/null 2>&1; }

# -----------------------------------------------------------------------------
# 1) Preflight
# -----------------------------------------------------------------------------
log "Preflight: $(pwd)"
[[ -d "${APP_DIR}" ]] || { fail "APP_DIR پیدا نشد: ${APP_DIR}"; exit 1; }
cd "${APP_DIR}"

[[ -f artisan ]] || { fail "artisan در ${APP_DIR} وجود ندارد"; exit 1; }
[[ -f .env ]]    || { fail ".env وجود ندارد؛ ابتدا طبق docs/DEPLOYMENT.md بخش ۶ بسازید"; exit 1; }

if [[ -z "${PHP_BIN}" ]]; then
    for candidate in php8.5 /usr/bin/php8.5 php; do
        if command -v "${candidate}" >/dev/null 2>&1; then
            PHP_BIN="$(command -v "${candidate}")"
            break
        fi
    done
fi
[[ -n "${PHP_BIN}" ]] || { fail "PHP پیدا نشد؛ PHP_BIN را تنظیم کنید"; exit 1; }
"${PHP_BIN}" -v >/dev/null 2>&1 || { fail "PHP اجرا نمی‌شود: ${PHP_BIN}"; exit 1; }

grep -q '^APP_KEY=.\+' .env || { fail "APP_KEY در .env تنظیم نشده است (php artisan key:generate)"; exit 1; }
grep -q '^APP_DEBUG=false' .env || warn "APP_DEBUG=false نیست؛ در production باید false باشد"

log "PHP: $("${PHP_BIN}" -v | head -n 1)"
log "App: $(grep -E '^APP_ENV=' .env || echo 'APP_ENV=?')"

# warn (نه fail) اگر Gotenberg در دسترس نیست؛ deploy نباید به آن وابسته باشد
if have curl; then
    if curl -fsS --max-time 5 -o /dev/null "http://127.0.0.1:3000/health"; then
        ok "Gotenberg روی loopback سالم است"
    else
        warn "Gotenberg روی 127.0.0.1:3000 پاسخ نداد؛ deploy ادامه می‌یابد اما تولید PDF خطا خواهد داشت"
        warn "  Gotenberg را خودتان ری‌استارت/نصب نکنید؛ با تیم زیرساخت هماهنگ کنید"
    fi
fi

# -----------------------------------------------------------------------------
# 2) Maintenance mode
# -----------------------------------------------------------------------------
if [[ "${SKIP_MAINTENANCE}" -eq 0 ]]; then
    log "فعال‌سازی maintenance mode (حداکثر 60 ثانیه)"
    run_artisan down --retry=60 || warn "down اجرا نشد (ادامه می‌دهیم)"
    MAINTENANCE_ACTIVE=1
else
    warn "SKIP_MAINTENANCE=1 — بدون maintenance mode"
fi

# -----------------------------------------------------------------------------
# 3) دریافت کد
# -----------------------------------------------------------------------------
if [[ "${SKIP_PULL}" -eq 0 ]]; then
    if [[ -d .git ]] && have git; then
        log "دریافت آخرین کد با git pull --ff-only"
        if [[ "${IS_ROOT}" -eq 1 ]]; then
            sudo -u www-data -- git pull --ff-only
        else
            git pull --ff-only
        fi
    elif [[ -n "${RSYNC_SOURCE}" ]] && have rsync; then
        log "همگام‌سازی با rsync از ${RSYNC_SOURCE}"
        rsync -az --delete \
            --exclude '.git' --exclude 'node_modules' --exclude '.env' \
            --exclude 'storage/app/private/documents/*' \
            --exclude 'storage/logs/*' \
            "${RSYNC_SOURCE}/" "${APP_DIR}/"
    else
        warn "نه git و نه RSYNC_SOURCE موجود نیست؛ مرحلهٔ دریافت کد رد شد"
    fi
else
    warn "SKIP_PULL=1 — دریافت کد رد شد"
fi

# -----------------------------------------------------------------------------
# 4) Composer
# -----------------------------------------------------------------------------
log "composer install --no-dev --optimize-autoloader"
if [[ "${IS_ROOT}" -eq 1 ]]; then
    COMPOSER_ALLOW_SUPERUSER=1 sudo -u www-data -- env COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
    COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev --optimize-autoloader --no-interaction --prefer-dist
fi

# -----------------------------------------------------------------------------
# 5) Migrations (همیشه قبل از آن بکاپ بگیرید — بخش ۱۷ DEPLOYMENT.md)
# -----------------------------------------------------------------------------
if [[ "${SKIP_MIGRATE}" -eq 0 ]]; then
    log "php artisan migrate --force"
    run_artisan migrate --force
else
    warn "SKIP_MIGRATE=1 — migrate رد شد"
fi

# -----------------------------------------------------------------------------
# 6) Front-end build
# -----------------------------------------------------------------------------
if [[ "${SKIP_NPM}" -eq 0 ]]; then
    if have npm; then
        log "npm ci && npm run build"
        run_npm ci --no-audit --no-fund
        run_npm run build
    else
        fail "npm پیدا نشد. یا Node نصب کنید، public/build را از CI منتقل کنید یا SKIP_NPM=1 بگذارید"
        exit 1
    fi
else
    warn "SKIP_NPM=1 — build فرانت‌اند رد شد (public/build باید موجود باشد)"
fi
[[ -d public/build ]] || warn "public/build موجود نیست؛ پنل/صفحات بدون asset کار می‌کنند؟"

# -----------------------------------------------------------------------------
# 7) Caches
# -----------------------------------------------------------------------------
log "پاک‌سازی و ساخت کش‌ها"
run_artisan optimize:clear
run_artisan config:cache
run_artisan route:cache
run_artisan view:cache
run_artisan event:cache || warn "event:cache در این نسخه پشتیبانی نمی‌شود (رد شد)"

# -----------------------------------------------------------------------------
# 8) Permissions
# -----------------------------------------------------------------------------
log "تنظیم مجوزها"
if [[ "${IS_ROOT}" -eq 1 ]] && id -u www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "${APP_DIR}"
fi
chmod -R ug+rwX storage bootstrap/cache
[[ -f .env ]] && chmod 640 .env

# -----------------------------------------------------------------------------
# 9) Queue worker env + restart
# -----------------------------------------------------------------------------
if [[ "${IS_ROOT}" -eq 1 ]]; then
    ENVFILE="/etc/default/pdf-service"
    log "نوشتن ${ENVFILE} از .env (PDF_QUEUE_*)"
    {
        echo "# Generated by deploy.sh at $(date -Iseconds) — do not edit manually"
        for key in PDF_QUEUE_NAME PDF_QUEUE_TRIES PDF_QUEUE_TIMEOUT PDF_QUEUE_BACKOFF; do
            value="$(grep -E "^${key}=" .env | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
            [[ -n "${value}" ]] && echo "${key}=${value}"
        done
    } > "${ENVFILE}"
    chmod 644 "${ENVFILE}"
fi

log "queue:restart"
run_artisan queue:restart || warn "queue:restart ناموفق بود"

if [[ "${IS_ROOT}" -eq 1 ]] && systemctl list-unit-files "${QUEUE_UNIT}" >/dev/null 2>&1; then
    log "restart ${QUEUE_UNIT}"
    systemctl restart "${QUEUE_UNIT}" || warn "restart ${QUEUE_UNIT} ناموفق بود"
fi

# -----------------------------------------------------------------------------
# 10) PHP-FPM graceful reload (نه restart!)
# -----------------------------------------------------------------------------
if [[ "${IS_ROOT}" -eq 1 ]] && systemctl list-unit-files "${FPM_SERVICE}.service" >/dev/null 2>&1; then
    log "reload ${FPM_SERVICE} (graceful، بدون restart)"
    systemctl reload "${FPM_SERVICE}" || warn "reload ${FPM_SERVICE} ناموفق بود"
fi

# -----------------------------------------------------------------------------
# 11) خروج از maintenance mode
# -----------------------------------------------------------------------------
log "خروج از maintenance mode"
run_artisan up
MAINTENANCE_ACTIVE=0

# -----------------------------------------------------------------------------
# 12) Health check
# -----------------------------------------------------------------------------
health_check() {
    local url="$1" name="$2" i
    for i in $(seq 1 "${HEALTH_RETRIES}"); do
        if curl -fsS --max-time 10 -o /dev/null "${url}"; then
            ok "${name}: ${url}"
            return 0
        fi
        sleep 2
    done
    fail "${name}: ${url} پاسخ نداد"
    return 1
}

if have curl; then
    log "Health check"
    health_check "https://${DOMAIN}/up" "app /up" || {
        warn "health check مستقیم HTTPS ناموفق؛ تلاش محلی..."
        health_check "http://127.0.0.1/up" "app /up (localhost)" || true
    }
    curl -fsS --max-time 15 "https://${DOMAIN}/api/v1/health" >/dev/null \
        && ok "API health: https://${DOMAIN}/api/v1/health" \
        || warn "API health پاسخ نداد (برای بررسی: docs/OPERATIONS.md بخش ۱)"
else
    warn "curl نصب نیست؛ health check رد شد"
fi

# -----------------------------------------------------------------------------
# 13) Summary
# -----------------------------------------------------------------------------
DURATION=$(( $(date +%s) - START_TS ))
ok "استقرار با موفقیت انجام شد (${DURATION} ثانیه)"
log "گام بعدی: تست یکپارچه با Gotenberg واقعی:"
log "  PDF_BASE_URL=https://${DOMAIN} PDF_API_KEY=<KEY> ./scripts/integration-test.sh"
log "  (کلید را در فایل ذخیره نکنید)"
