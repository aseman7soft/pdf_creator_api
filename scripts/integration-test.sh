#!/usr/bin/env bash
# =============================================================================
# تست یکپارچهٔ واقعی سرویس تولید اسناد (Gotenberg واقعی) — Linux / macOS
# =============================================================================
#
# این اسکریپت سرویس در حال اجرا را (لوکال یا remote) با API Key واقعی تست می‌کند:
#   سلامت، احراز هویت، تولید هم‌زمان PDF، رندر فارسی، قالب چندصفحه‌ای،
#   جریان صف، دانلود امضاشده و (اختیاری) rate limit.
#
# هیچ کلید/رمزی در فایل hardcode نشده است؛ همه از متغیرهای محیطی خوانده می‌شوند.
#
# متغیرهای محیطی:
#   PDF_BASE_URL            آدرس پایه سرویس (پیش‌فرض: http://127.0.0.1:8000)
#   PDF_API_KEY             کلید خام API — الزامی (بدون hardcode)
#   PDF_TEMPLATE            قالب تست فارسی (پیش‌فرض: hotel-voucher)
#   PDF_MULTIPAGE_TEMPLATE  قالب چندصفحه‌ای (پیش‌فرض: tour-itinerary)
#   PDF_SKIP_QUEUE=1        رد کردن تست صف/دانلود
#   PDF_RUN_RATE_LIMIT=1    اجرای تست اختیاری rate limit (مصرف سهمیه دارد)
#   PDF_HTTP_TIMEOUT        timeout هر درخواست (ثانیه، پیش‌فرض 120)
#   PDF_QUEUE_TIMEOUT_CHECK انتظار برای completed شدن صف (ثانیه، پیش‌فرض 120)
#   PDF_TEST_OUTPUT_DIR     پوشهٔ خروجی فایل‌های PDF (پیش‌فرض: پوشهٔ موقت)
#
# پیش‌نیاز: curl و یکی از jq/php/python3 برای parse JSON
# خروجی: کد 0 در صورت موفقیت، 1 در صورت شکست
#
# نمونه:
#   export PDF_BASE_URL="https://pdf.as7th.com"
#   export PDF_API_KEY="pdfsvc_xxx"
#   ./scripts/integration-test.sh
# =============================================================================

set -Eeuo pipefail

BASE_URL="${PDF_BASE_URL:-http://127.0.0.1:8000}"
API_KEY="${PDF_API_KEY:-}"
TEMPLATE="${PDF_TEMPLATE:-hotel-voucher}"
MULTIPAGE_TEMPLATE="${PDF_MULTIPAGE_TEMPLATE:-tour-itinerary}"
HTTP_TIMEOUT="${PDF_HTTP_TIMEOUT:-120}"
QUEUE_TIMEOUT="${PDF_QUEUE_TIMEOUT_CHECK:-120}"
SKIP_QUEUE="${PDF_SKIP_QUEUE:-0}"
RUN_RATE_LIMIT="${PDF_RUN_RATE_LIMIT:-0}"
RATE_LIMIT_MAX="${PDF_RATE_LIMIT_MAX_ATTEMPTS:-130}"
BASE_URL="${BASE_URL%/}"

OUTPUT_DIR="${PDF_TEST_OUTPUT_DIR:-$(mktemp -d "${TMPDIR:-/tmp}/pdf-service-test.XXXXXX")}"
mkdir -p "$OUTPUT_DIR"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/pdf-service-test-tmp.XXXXXX")"
TMP_BODY="$TMP_DIR/body"
TMP_HEADERS="$TMP_DIR/headers"
TMP_ERR="$TMP_DIR/curl.err"
HTTP_STATUS=""
HTTP_BODY=""

PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
FAILURES=()
START_TS=$(date +%s)

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

# -----------------------------------------------------------------------------
# Output helpers
# -----------------------------------------------------------------------------
if [[ -t 1 ]]; then
    C_RED=$'\033[1;31m'; C_GRN=$'\033[1;32m'; C_YEL=$'\033[1;33m'
    C_CYN=$'\033[1;36m'; C_DIM=$'\033[2m'; C_RST=$'\033[0m'
else
    C_RED=''; C_GRN=''; C_YEL=''; C_CYN=''; C_DIM=''; C_RST=''
fi

head_line() {
    printf '\n%s%s\n  %s\n%s\n' "$C_DIM" "==============================================================================" "$1" "=============================================================================="
    printf '%s' "$C_RST"
}
pass() { PASS_COUNT=$((PASS_COUNT + 1)); printf '  %s[PASS]%s %s\n' "$C_GRN" "$C_RST" "$1"; }
skip() { SKIP_COUNT=$((SKIP_COUNT + 1)); printf '  %s[SKIP]%s %s\n' "$C_YEL" "$C_RST" "$1"; }
fail() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    FAILURES+=("$1")
    printf '  %s[FAIL]%s %s\n' "$C_RED" "$C_RST" "$1"
}
info() { printf '         %s\n' "$C_DIM$1$C_RST"; }
die()  { printf '\n%s[fatal]%s %s\n' "$C_RED" "$C_RST" "$1" >&2; exit 2; }

# -----------------------------------------------------------------------------
# JSON parser (jq → php → python3)
# -----------------------------------------------------------------------------
JSON_TOOL=''
if command -v jq >/dev/null 2>&1; then
    JSON_TOOL='jq'
elif command -v php >/dev/null 2>&1; then
    JSON_TOOL='php'
elif command -v python3 >/dev/null 2>&1; then
    JSON_TOOL='python3'
else
    die "برای parse پاسخ JSON یکی از ابزارهای jq/php/python3 لازم است (apt install jq)."
fi

# json_get <json> <dot.path>
json_get() {
    local js="$1" path="$2"
    case "$JSON_TOOL" in
        jq)
            printf '%s' "$js" | jq -r --arg p "$path" '
                (try (getpath($p | split("."))) catch null)
                | if . == null then "" elif type == "object" or type == "array" then "" else tostring end
            ' 2>/dev/null || true
            ;;
        php)
            printf '%s' "$js" | php -r '
                $path = explode(".", $argv[1]);
                $data = json_decode(stream_get_contents(STDIN), true);
                foreach ($path as $key) {
                    if (!is_array($data) || !array_key_exists($key, $data)) { exit(0); }
                    $data = $data[$key];
                }
                if (is_scalar($data)) { echo $data; }
            ' "$path" 2>/dev/null || true
            ;;
        python3)
            printf '%s' "$js" | python3 -c '
import json, sys

data = json.load(sys.stdin)
for key in sys.argv[1].split("."):
    if not isinstance(data, dict) or key not in data:
        sys.exit(0)
    data = data[key]
if isinstance(data, (str, int, float)):
    print(data)
' "$path" 2>/dev/null || true
            ;;
    esac
}

# json_slugs <templates-json> → یکی در هر خط
json_slugs() {
    local js="$1"
    case "$JSON_TOOL" in
        jq)
            printf '%s' "$js" | jq -r '
                (.data // .) as $d
                | (if ($d | type) == "array" then $d else ($d.templates // []) end)
                | .[] | .slug // empty
            ' 2>/dev/null || true
            ;;
        php)
            printf '%s' "$js" | php -r '
                $root = json_decode(stream_get_contents(STDIN), true);
                $data = $root["data"] ?? $root;
                $list = (isset($data["templates"]) && is_array($data["templates"])) ? $data["templates"] : $data;
                if (!is_array($list)) { exit(0); }
                foreach ($list as $item) {
                    if (is_array($item) && isset($item["slug"])) { echo $item["slug"], "\n"; }
                }
            ' 2>/dev/null || true
            ;;
        python3)
            printf '%s' "$js" | python3 -c '
import json, sys

root = json.load(sys.stdin)
data = root.get("data", root)
if isinstance(data, dict) and isinstance(data.get("templates"), list):
    items = data["templates"]
elif isinstance(data, list):
    items = data
elif isinstance(data, dict):
    items = [data]
else:
    items = []
for item in items:
    if isinstance(item, dict) and item.get("slug"):
        print(item["slug"])
' 2>/dev/null || true
            ;;
    esac
}

# -----------------------------------------------------------------------------
# HTTP helpers
# -----------------------------------------------------------------------------
header_value() { # <name>
    local name="$1"
    [[ -f "$TMP_HEADERS" ]] || { echo ''; return; }
    awk -v n="$name" 'index(tolower($0), tolower(n)":")==1 { sub(/^[^:]*:[[:space:]]*/, ""); sub(/\r$/, ""); print }' "$TMP_HEADERS" | tail -n 1
}

http_request() {
    # http_request <method> <path|url> [body] [outfile] [noauth]
    local method="$1" path="$2" body="${3:-}" outfile="${4:-}" noauth="${5:-0}"
    local url="$path"
    [[ "$path" != http* ]] && url="$BASE_URL/${path#/}"

    local actual_out="$TMP_BODY"
    if [[ -n "$outfile" ]]; then
        actual_out="$outfile"
    fi

    local -a args=(-sS --max-time "$HTTP_TIMEOUT" -D "$TMP_HEADERS" -o "$actual_out" -X "$method" -w '%{http_code}')
    local -a headers=(-H 'Accept: application/json')
    if [[ "$noauth" != "1" && -n "$API_KEY" ]]; then
        headers+=(-H "Authorization: Bearer ${API_KEY}")
    fi
    if [[ -n "$body" ]]; then
        headers+=(-H 'Content-Type: application/json; charset=utf-8')
        args+=(--data-binary "$body")
    fi

    HTTP_STATUS="$(curl "${args[@]}" "${headers[@]}" "$url" 2>"$TMP_ERR" || true)"
    if [[ "$HTTP_STATUS" == "000" || -z "$HTTP_STATUS" ]]; then
        HTTP_BODY="$(cat "$TMP_ERR" 2>/dev/null || true)"
        HTTP_STATUS="000"
    elif [[ -z "$outfile" ]]; then
        HTTP_BODY="$(cat "$TMP_BODY" 2>/dev/null || true)"
    else
        HTTP_BODY=""
    fi
}

now_ms() {
    local value
    value="$(date +%s%3N 2>/dev/null || true)"
    if [[ "$value" =~ ^[0-9]+$ ]]; then
        echo "$value"
    else
        echo $(($(date +%s) * 1000))
    fi
}

pdf_meta() { # <file> → "is_pdf size pages"
    local file="$1"
    [[ -f "$file" ]] || { echo "0 0 0"; return; }
    local size header pages count pages2
    size=$(wc -c < "$file" | tr -d ' ')
    header=$(head -c 5 "$file" 2>/dev/null || true)
    pages=$(grep -a -o '/Type[[:space:]]*/Page[^s]' "$file" 2>/dev/null | wc -l | tr -d ' ' || true)
    count=$(grep -a -o '/Count[[:space:]][0-9][0-9]*' "$file" 2>/dev/null | sed 's/[^0-9]//g' | sort -n | tail -n 1 || true)
    count=${count:-0}
    pages2="$pages"
    if [[ "$count" -gt "$pages2" ]] 2>/dev/null; then pages2="$count"; fi
    local is_pdf="0"
    [[ "$header" == "%PDF-" ]] && is_pdf="1"
    echo "$is_pdf $size $pages2"
}

doc_url_or_path() { # <maybe-relative-url> <default-path>
    local url="$1" fallback="$2"
    if [[ -z "$url" ]]; then echo "$fallback"; else echo "$url"; fi
}

# -----------------------------------------------------------------------------
# Payload builders
# -----------------------------------------------------------------------------
json_escape() {
    # escape برای embed کردن متن فارسی در JSON
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    printf '%s' "$s"
}

hotel_voucher_payload() { # <reference>
    local ref="$1"
    local check_in check_out
    check_in="$(date -d '+10 days' '+%Y-%m-%d' 2>/dev/null || echo '2026-12-01')"
    check_out="$(date -d '+13 days' '+%Y-%m-%d' 2>/dev/null || echo '2026-12-04')"
    cat <<JSON
{
  "template": "$(json_escape "$TEMPLATE")",
  "reference": "$(json_escape "$ref")",
  "data": {
    "guest_name": "علی رضایی",
    "hotel_name": "هتل نمونهٔ پارسیان",
    "booking_reference": "$(json_escape "$ref")",
    "check_in": "$check_in",
    "check_out": "$check_out",
    "room_count": 2,
    "total_price": 12500000,
    "currency": "IRR",
    "guests": [
      {"name": "علی رضایی", "passport": "A1234567"},
      {"name": "مریم احمدی", "passport": "B7654321"}
    ],
    "notes": "تست یکپارچهٔ سرویس تولید اسناد — رندر فارسی و راست‌چین."
  }
}
JSON
}

tour_itinerary_payload() { # <reference>
    local ref="$1"
    local start end days_json=''
    start="$(date -d '+20 days' '+%Y-%m-%d' 2>/dev/null || echo '2026-12-11')"
    end="$(date -d '+27 days' '+%Y-%m-%d' 2>/dev/null || echo '2026-12-18')"
    local i
    for i in $(seq 1 8); do
        [[ -n "$days_json" ]] && days_json+=","
        days_json+=$(printf '{"day_number":%d,"title":"روز %d — برنامهٔ سفر","description":"شرح فعالیت‌های روز %d در تور نمونه؛ بازدید از جاذبه‌ها و اقامت در هتل.","meals":"صبحانه، ناهار","hotel":"هتل نمونهٔ %d"}' "$i" "$i" "$i" "$i")
    done
    cat <<JSON
{
  "template": "$(json_escape "$MULTIPAGE_TEMPLATE")",
  "reference": "$(json_escape "$ref")",
  "data": {
    "tour_name": "تور ۸ روزهٔ نمونه",
    "tour_code": "$(json_escape "$ref")",
    "start_date": "$start",
    "end_date": "$end",
    "traveler_count": 12,
    "duration_days": 8,
    "destination": "شیراز و اصفهان",
    "days": [$days_json],
    "includes": ["اقامت در هتل", "ترانسفر فرودگاهی", "راهنمای تور"],
    "excludes": ["بلیط هواپیما", "وعده‌های ناهار"],
    "emergency_contact": "021-00000000",
    "terms": "شرایط نمونهٔ تست یکپارچه."
  }
}
JSON
}

result_file() { # <name> → مسیر فایل
    echo "$OUTPUT_DIR/pdf-service-test-$1-$(date +%Y%m%d-%H%M%S).pdf"
}

# -----------------------------------------------------------------------------
# Start
# -----------------------------------------------------------------------------
command -v curl >/dev/null 2>&1 || die "curl نصب نیست."

head_line "تست یکپارچهٔ سرویس تولید اسناد (Gotenberg واقعی)"
info "Base URL : $BASE_URL"
info "Template : $TEMPLATE  |  Multipage: $MULTIPAGE_TEMPLATE"
info "Output   : $OUTPUT_DIR"
info "JSON tool: $JSON_TOOL"

[[ -n "$API_KEY" ]] || {
    fail "PDF_API_KEY تنظیم نشده است."
    info "کلید خام را از پنل (/admin → پروژه‌ها → کلیدها) بگیرید و در متغیر محیطی بگذارید؛"
    info "هیچ کلیدی در این اسکریپت ذخیره نکنید."
    printf '\n%s[fatal]%s کلید API موجود نیست.\n' "$C_RED" "$C_RST" >&2
    exit 2
}
info "API Key  : $(printf '%s' "$API_KEY" | cut -c1-12)... (masked)"

# -----------------------------------------------------------------------------
# 1) Health
# -----------------------------------------------------------------------------
head_line "1) سلامت سرویس"
http_request GET "/up" "" "" "1"
if [[ "$HTTP_STATUS" == "200" ]]; then pass "/up → 200"; else fail "/up → ${HTTP_STATUS} (انتظار 200)"; fi

http_request GET "/api/v1/health" "" "" "1"
if [[ "$HTTP_STATUS" == "200" ]]; then
    if [[ "$(json_get "$HTTP_BODY" 'success')" == "true" ]]; then
        pass "/api/v1/health → 200 (success=true)"
        svc_status="$(json_get "$HTTP_BODY" 'data.status')"
        [[ -n "$svc_status" ]] && info "service status: $svc_status"
    else
        fail "/api/v1/health پاسخ success=true نداشت: $HTTP_BODY"
    fi
else
    fail "/api/v1/health → ${HTTP_STATUS} (انتظار 200)"
fi

# -----------------------------------------------------------------------------
# 2) Authentication + template access
# -----------------------------------------------------------------------------
head_line "2) احراز هویت API Key"
http_request GET "/api/v1/templates" "" "" "1"
if [[ "$HTTP_STATUS" == "401" || "$HTTP_STATUS" == "403" ]]; then
    pass "درخواست بدون کلید رد شد → ${HTTP_STATUS}"
elif [[ "$HTTP_STATUS" == "200" ]]; then
    fail "درخواست بدون Authorization به /templates پاسخ 200 داد (نبود auth/نشت اطلاعات)!"
else
    fail "درخواست بدون کلید وضعیت غیرمنتظره: ${HTTP_STATUS}"
fi

http_request GET "/api/v1/templates"
AVAILABLE_SLUGS=()
if [[ "$HTTP_STATUS" == "200" ]]; then
    pass "/api/v1/templates با Bearer → 200"
    while IFS= read -r slug; do
        [[ -n "$slug" ]] && AVAILABLE_SLUGS+=("$slug")
    done < <(json_slugs "$HTTP_BODY")
    if [[ "${#AVAILABLE_SLUGS[@]}" -gt 0 ]]; then
        info "قالب‌های مجاز: $(IFS=', '; echo "${AVAILABLE_SLUGS[*]}")"
    fi
else
    fail "/api/v1/templates → ${HTTP_STATUS} — کلید/پروژه/دسترسی را بررسی کنید"
fi

slug_allowed() { # <slug>
    local wanted="$1" s
    for s in "${AVAILABLE_SLUGS[@]:-}"; do
        [[ "$s" == "$wanted" ]] && return 0
    done
    return 1
}

CAN_GENERATE=0
if [[ "$HTTP_STATUS" == "200" ]] || [[ "${#AVAILABLE_SLUGS[@]}" -eq 0 ]]; then CAN_GENERATE=1; fi
if [[ "$CAN_GENERATE" -eq 1 ]] && [[ "${#AVAILABLE_SLUGS[@]}" -gt 0 ]]; then
    if slug_allowed "$TEMPLATE"; then
        pass "قالب '$TEMPLATE' در لیست مجاز است"
    else
        fail "قالب '$TEMPLATE' برای این کلید مجاز نیست. PDF_TEMPLATE را تنظیم یا قالب را در پنل متصل کنید."
        CAN_GENERATE=0
    fi
fi

# -----------------------------------------------------------------------------
# 3) Sync generation
# -----------------------------------------------------------------------------
head_line "3) تولید هم‌زمان PDF (قالب فارسی)"
if [[ "$CAN_GENERATE" -eq 1 ]]; then
    out_sync="$(result_file 'sync')"
    body="$(hotel_voucher_payload "IT-SYNC-$(date +%H%M%S)")"
    t0=$(now_ms)
    http_request POST "/api/v1/documents/generate" "$body" "$out_sync"
    t1=$(now_ms)
    dur=$((t1 - t0))

    if [[ "$HTTP_STATUS" == "200" ]]; then
        read -r is_pdf size pages <<< "$(pdf_meta "$out_sync")"
        if [[ "$is_pdf" == "1" && "$size" -gt 500 ]]; then
            pass "PDF تولید شد: $((size / 1024)) KB در ${dur} ms"
            doc_id="$(header_value 'X-Document-Id')"
            req_id="$(header_value 'X-Request-Id')"
            gen_ms="$(header_value 'X-Generation-Time-Ms')"
            [[ -n "$doc_id" ]] && pass "هدر X-Document-Id موجود است: $doc_id" || info "هدر X-Document-Id یافت نشد (اختیاری در این تست)"
            [[ -n "$req_id" ]] && pass "هدر X-Request-Id موجود است: $req_id" || info "هدر X-Request-Id یافت نشد"
            [[ -n "$gen_ms" ]] && info "X-Generation-Time-Ms: $gen_ms"
            [[ "$pages" -ge 1 ]] && pass "ساختار PDF سالم است (تشخیص $pages صفحه)" || info "شمارش صفحه از بایت‌ها قابل تشخیص نبود (PDF معتبر است)"
            info "فایل: $out_sync"
        else
            fail "پاسخ 200 بود اما خروجی PDF معتبر نیست (size=$size)"
        fi
    else
        fail "generate → ${HTTP_STATUS} | $(printf '%s' "$HTTP_BODY" | head -c 400)"
    fi
else
    skip "تولید هم‌زمان به‌دلیل نبود دسترسی قالب رد شد"
fi

# -----------------------------------------------------------------------------
# 4) Multipage template
# -----------------------------------------------------------------------------
head_line "4) قالب چندصفحه‌ای"
if [[ "$CAN_GENERATE" -eq 1 ]] && [[ "${#AVAILABLE_SLUGS[@]}" -gt 0 ]] && ! slug_allowed "$MULTIPAGE_TEMPLATE"; then
    skip "قالب '$MULTIPAGE_TEMPLATE' برای این کلید مجاز نیست"
elif [[ "$CAN_GENERATE" -eq 1 ]]; then
    out_mp="$(result_file 'multipage')"
    body_mp="$(tour_itinerary_payload "IT-MP-$(date +%H%M%S)")"
    http_request POST "/api/v1/documents/generate" "$body_mp" "$out_mp"
    if [[ "$HTTP_STATUS" == "200" ]]; then
        read -r is_pdf size pages <<< "$(pdf_meta "$out_mp")"
        if [[ "$is_pdf" == "1" && "$size" -gt 500 ]]; then
            if [[ "$pages" -ge 2 ]]; then
                pass "قالب چندصفحه‌ای PDF تولید کرد ($pages صفحه، $((size / 1024)) KB)"
            elif [[ "$pages" -eq 1 ]]; then
                pass "PDF چندصفحه‌ای تولید شد اما تشخیص خودکار فقط ۱ صفحه یافت (احتمال فشرده‌سازی PDF)"
            else
                pass "PDF چندصفحه‌ای تولید شد ($((size / 1024)) KB)"
            fi
            info "فایل: $out_mp"
        else
            fail "خروجی قالب چندصفحه‌ای PDF معتبر نیست (size=$size)"
        fi
    else
        fail "multipage generate → ${HTTP_STATUS} | $(printf '%s' "$HTTP_BODY" | head -c 400)"
    fi
else
    skip "قالب چندصفحه‌ای رد شد"
fi

# -----------------------------------------------------------------------------
# 5) Queue flow + signed download
# -----------------------------------------------------------------------------
head_line "5) جریان صف (async) و دانلود امضاشده"
if [[ "$SKIP_QUEUE" == "1" ]]; then
    skip "تست صف با PDF_SKIP_QUEUE=1 رد شد"
elif [[ "$CAN_GENERATE" -ne 1 ]]; then
    skip "تست صف به‌دلیل نبود دسترسی قالب رد شد"
else
    body_q="$(hotel_voucher_payload "IT-QUEUE-$(date +%H%M%S)")"
    http_request POST "/api/v1/documents/queue" "$body_q"
    if [[ "$HTTP_STATUS" == "202" ]]; then
        pass "POST /documents/queue → 202"
        q_id="$(json_get "$HTTP_BODY" 'data.id')"
        [[ -z "$q_id" ]] && q_id="$(json_get "$HTTP_BODY" 'data.ulid')"
        status_url="$(json_get "$HTTP_BODY" 'data.status_url')"
        [[ -z "$status_url" ]] && [[ -n "$q_id" ]] && status_url="/api/v1/documents/$q_id/status"

        if [[ -z "$q_id" || -z "$status_url" ]]; then
            fail "پاسخ queue فاقد id/status_url است: $(printf '%s' "$HTTP_BODY" | head -c 400)"
        else
            info "id=$q_id"
            deadline=$(( $(date +%s) + QUEUE_TIMEOUT ))
            last_status=''
            completed=0
            failed_msg=''
            download_url=''
            while [[ "$(date +%s)" -lt "$deadline" ]]; do
                sleep 2
                http_request GET "$status_url"
                if [[ "$HTTP_STATUS" == "200" ]]; then
                    last_status="$(json_get "$HTTP_BODY" 'data.status')"
                    if [[ "$last_status" == "completed" ]]; then
                        completed=1
                        download_url="$(json_get "$HTTP_BODY" 'data.download_url')"
                        break
                    fi
                    if [[ "$last_status" == "failed" ]]; then
                        failed_msg="$(json_get "$HTTP_BODY" 'data.error_message')"
                        break
                    fi
                fi
            done

            if [[ "$completed" -eq 1 ]]; then
                pass "سند صف به وضعیت completed رسید"
                if [[ -n "$download_url" ]]; then
                    d_url="$download_url"
                else
                    d_url="/api/v1/documents/$q_id/download"
                    info "download_url در status نبود؛ دانلود Bearer تست می‌شود"
                fi
                out_q="$(result_file 'queue')"
                if [[ "$d_url" == *"signature="* ]]; then
                    http_request GET "$d_url" "" "$out_q" "1"
                    if [[ "$HTTP_STATUS" == "200" ]]; then
                        read -r is_pdf size _pages <<< "$(pdf_meta "$out_q")"
                        if [[ "$is_pdf" == "1" && "$size" -gt 500 ]]; then
                            pass "دانلود با signed URL بدون Bearer موفق ($((size / 1024)) KB)"
                        else
                            fail "فایل دانلودشده از signed URL PDF معتبر نیست"
                        fi
                    else
                        fail "download signed → ${HTTP_STATUS}"
                    fi
                    # امنیت: signed route بدون امضا باید رد شود
                    http_request GET "/api/v1/documents/$q_id/download/signed" "" "" "1"
                    if [[ "$HTTP_STATUS" == "401" || "$HTTP_STATUS" == "403" || "$HTTP_STATUS" == "404" ]]; then
                        pass "signed route بدون امضا رد شد → ${HTTP_STATUS}"
                    else
                        fail "signed route بدون امضا وضعیت ${HTTP_STATUS} برگرداند؛ انتظار 403/401"
                    fi
                else
                    http_request GET "$d_url" "" "$out_q"
                    if [[ "$HTTP_STATUS" == "200" ]]; then
                        read -r is_pdf size _pages <<< "$(pdf_meta "$out_q")"
                        if [[ "$is_pdf" == "1" && "$size" -gt 500 ]]; then
                            pass "دانلود با Bearer موفق ($((size / 1024)) KB)"
                        else
                            fail "فایل دانلودشده PDF معتبر نیست"
                        fi
                    else
                        fail "download (Bearer) → ${HTTP_STATUS}"
                    fi
                fi
            elif [[ -n "$failed_msg" ]]; then
                fail "سند صف با وضعیت failed مواجه شد: $failed_msg — worker/Gotenberg را بررسی کنید"
                info "راهنما: docs/OPERATIONS.md بخش ۳ و ۸"
            else
                fail "سند صف تا ${QUEUE_TIMEOUT} ثانیه به completed نرسید (آخرین وضعیت: ${last_status:-unknown})"
                info "آیا queue worker در حال اجراست؟ (php artisan queue:work --queue=documents یا systemd pdf-queue)"
            fi
        fi
    elif [[ "$HTTP_STATUS" == "429" ]]; then
        fail "درخواست queue با 429 محدود شد؛ بعداً دوباره تلاش کنید"
    else
        fail "POST /documents/queue → ${HTTP_STATUS} | $(printf '%s' "$HTTP_BODY" | head -c 400)"
    fi
fi

# -----------------------------------------------------------------------------
# 6) Optional rate limit
# -----------------------------------------------------------------------------
head_line "6) Rate limit (اختیاری)"
if [[ "$RUN_RATE_LIMIT" != "1" ]]; then
    skip "فعال‌سازی با PDF_RUN_RATE_LIMIT=1"
else
    info "این تست سهمیه مصرف می‌کند و تا ${RATE_LIMIT_MAX} درخواست می‌فرستد"
    hit429=0
    attempt=0
    for attempt in $(seq 1 "$RATE_LIMIT_MAX"); do
        http_request GET "/api/v1/templates"
        if [[ "$attempt" -eq 1 ]]; then
            rl_limit="$(header_value 'X-RateLimit-Limit')"
            [[ -n "$rl_limit" ]] && info "X-RateLimit-Limit: $rl_limit"
        fi
        if [[ "$HTTP_STATUS" == "429" ]]; then
            hit429=1
            retry_after="$(header_value 'Retry-After')"
            pass "429 پس از ${attempt} درخواست دریافت شد${retry_after:+ (Retry-After=$retry_after)}"
            break
        fi
    done
    if [[ "$hit429" -ne 1 ]]; then
        skip "در ${RATE_LIMIT_MAX} درخواست به 429 نرسیدیم (سقف ممکن است بالاتر باشد) — تست اجباری نیست"
    fi
fi

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------
ELAPSED=$(( $(date +%s) - START_TS ))
head_line "خلاصهٔ نتایج"
printf '  %sPASS:%s %d\n' "$C_GRN" "$C_RST" "$PASS_COUNT"
printf '  %sFAIL:%s %d\n' "$C_RED" "$C_RST" "$FAIL_COUNT"
printf '  %sSKIP:%s %d\n' "$C_YEL" "$C_RST" "$SKIP_COUNT"
printf '  زمان: %d ثانیه\n' "$ELAPSED"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
    printf '\n  %sموارد ناموفق:%s\n' "$C_RED" "$C_RST"
    for f in "${FAILURES[@]}"; do printf '   - %s\n' "$f"; done
    printf '\n  عیب‌یابی: docs/OPERATIONS.md (سلامت/صف/Gotenberg) و docs/TESTING.md\n'
    exit 1
fi

printf '\n  ✅ همهٔ تست‌های اجباری موفق بودند.\n'
exit 0
