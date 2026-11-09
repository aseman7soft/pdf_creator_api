#!/usr/bin/bash
# CI deploy for pdf_creator_api on auto2 self-hosted runner (user: pdf-runner)
set -euo pipefail
APP_DIR=/var/www/pdf-service
DOMAIN=pdf.as7th.com
BALE_TOKEN_FILE="${BALE_TOKEN_FILE:-/home/pdf-runner/.bale_token}"
log(){ printf "[%s] %s\n" "$(date +%H:%M:%S)" "$*"; }
cd "$APP_DIR"
BEFORE=$(git rev-parse --short HEAD)
log "fetch/reset main (was $BEFORE)"
git fetch --prune origin
git reset --hard origin/main
export DEPLOY_SHA=$(git rev-parse --short HEAD)
export DEPLOY_FULL=$(git rev-parse HEAD)
export DEPLOY_MSG=$(git log -1 --pretty=%s)
export DEPLOY_AUTHOR=$(git log -1 --pretty='%an')
export DEPLOY_BRANCH=$(git rev-parse --abbrev-ref HEAD)
export DEPLOY_TIME=$(TZ=Asia/Tehran date '+%Y-%m-%d %H:%M:%S %Z')
log "composer install"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
log "migrate"
php8.5 artisan migrate --force
if command -v npm >/dev/null 2>&1; then
  log "npm ci && build"
  npm ci --no-audit --no-fund
  npm run build
else
  log "npm missing — keeping existing public/build"
fi
log "caches"
php8.5 artisan optimize:clear
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan view:cache || true
log "restart queue / reload php-fpm"
sudo /usr/bin/systemctl restart pdf-queue.service
sudo /usr/bin/systemctl reload php8.5-fpm
log "health"
curl -fsS -m 15 -H "Host: ${DOMAIN}" http://127.0.0.1/up >/dev/null
curl -fsS -m 15 -H "Host: ${DOMAIN}" http://127.0.0.1/api/v1/health >/dev/null || true
log "deployed ${DEPLOY_SHA}"
if [[ -f "$BALE_TOKEN_FILE" ]]; then
  python3 - <<'PY'
import json, os, urllib.request
token = open(os.environ.get("BALE_TOKEN_FILE", "/home/pdf-runner/.bale_token"), encoding="utf-8").read().strip()
text = (
    "✅ Deploy موفق pdf_creator_api\n"
    f"repo: aseman7soft/pdf_creator_api\n"
    f"branch: {os.environ.get('DEPLOY_BRANCH','')}\n"
    f"sha: {os.environ.get('DEPLOY_SHA','')}\n"
    f"msg: {os.environ.get('DEPLOY_MSG','')}\n"
    f"author: {os.environ.get('DEPLOY_AUTHOR','')}\n"
    f"time: {os.environ.get('DEPLOY_TIME','')}"
)
payload = json.dumps({"chat_id": 1136493564, "text": text}).encode()
req = urllib.request.Request(
    f"https://tapi.bale.ai/bot{token}/sendMessage",
    data=payload,
    headers={"Content-Type": "application/json"},
    method="POST",
)
try:
    with urllib.request.urlopen(req, timeout=20) as r:
        print("bale_status", r.status)
except Exception as e:
    print("bale_error", type(e).__name__)
PY
else
  log "Bale token file missing — skip notify"
fi
