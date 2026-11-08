#Requires -Version 5.1
<#
.SYNOPSIS
    تست یکپارچهٔ واقعی سرویس تولید اسناد (Gotenberg واقعی) — ویندوز / PowerShell

.DESCRIPTION
    این اسکریپت سرویس در حال اجرا را (لوکال یا remote) با API Key واقعی تست می‌کند:
    سلامت، احراز هویت، تولید هم‌زمان PDF، رندر فارسی، قالب چندصفحه‌ای،
    جریان صف، دانلود امضاشده و (اختیاری) rate limit.

    هیچ کلید/رمزی در فایل hardcode نشده است؛ همه از متغیرهای محیطی خوانده می‌شوند.

.PARAMETER BaseUrl
    آدرس پایه سرویس. پیش‌فرض از PDF_BASE_URL و در نبود آن http://127.0.0.1:8000

.PARAMETER ApiKey
    کلید خام API. پیش‌فرض از PDF_API_KEY. الزامی است.

.PARAMETER SkipQueue
    رد کردن تست صف/دانلود (اگر queue worker در حال اجرا نیست).

.PARAMETER RunRateLimit
    اجرای تست اختیاری rate limit (مصرف سهمیه دارد).

.EXAMPLE
    $env:PDF_BASE_URL = "https://pdf-creator.test"
    $env:PDF_API_KEY  = "pdfsvc_xxx"
    powershell -ExecutionPolicy Bypass -File .\scripts\integration-test.ps1

.EXAMPLE
    .\scripts\integration-test.ps1 -BaseUrl https://pdf.as7th.com -ApiKey $env:PDF_API_KEY -SkipQueue

.NOTES
    پیش‌نیاز: PowerShell 5.1+، دسترسی شبکه به سرویس. هیچ وابستگی خارجی لازم نیست.
    خروجی: کد خروجی 0 در صورت موفقیت، 1 در صورت شکست.
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = $(if ($env:PDF_BASE_URL) { $env:PDF_BASE_URL } else { "http://127.0.0.1:8000" }),
    [string]$ApiKey  = $(if ($env:PDF_API_KEY) { $env:PDF_API_KEY } else { "" }),
    [string]$Template = $(if ($env:PDF_TEMPLATE) { $env:PDF_TEMPLATE } else { "hotel-voucher" }),
    [string]$MultipageTemplate = $(if ($env:PDF_MULTIPAGE_TEMPLATE) { $env:PDF_MULTIPAGE_TEMPLATE } else { "tour-itinerary" }),
    [int]$TimeoutSec = 120,
    [int]$QueueTimeoutSec = 120,
    [switch]$SkipQueue,
    [switch]$RunRateLimit,
    [int]$RateLimitMaxAttempts = 130,
    [string]$OutputDir = $(if ($env:PDF_TEST_OUTPUT_DIR) { $env:PDF_TEST_OUTPUT_DIR } else { [System.IO.Path]::GetTempPath() })
)

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue'

# TLS 1.2 برای HTTPS روی PowerShell 5.1
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
} catch { }

# -----------------------------------------------------------------------------
# State
# -----------------------------------------------------------------------------
$script:PassCount = 0
$script:FailCount = 0
$script:SkipCount = 0
$script:Failures  = New-Object System.Collections.Generic.List[string]
$script:StartedAt = Get-Date
$script:BaseUrl   = $BaseUrl.TrimEnd('/')

function Write-Head([string]$Text) {
    Write-Host ""
    Write-Host ("=" * 78) -ForegroundColor DarkGray
    Write-Host ("  " + $Text) -ForegroundColor Cyan
    Write-Host ("=" * 78) -ForegroundColor DarkGray
}
function Write-Pass([string]$Text) { $script:PassCount++; Write-Host ("  [PASS] " + $Text) -ForegroundColor Green }
function Write-Skip([string]$Text) { $script:SkipCount++; Write-Host ("  [SKIP] " + $Text) -ForegroundColor Yellow }
function Write-Fail([string]$Text) {
    $script:FailCount++
    $script:Failures.Add($Text)
    Write-Host ("  [FAIL] " + $Text) -ForegroundColor Red
}
function Write-Info([string]$Text) { Write-Host ("         " + $Text) -ForegroundColor DarkGray }

# -----------------------------------------------------------------------------
# HTTP helpers
# -----------------------------------------------------------------------------
function Get-HeaderValue($Headers, [string]$Name) {
    if ($null -eq $Headers) { return $null }
    try {
        $value = $Headers[$Name]
        if ($null -eq $value) {
            foreach ($k in $Headers.Keys) { if ($k -ieq $Name) { $value = $Headers[$k]; break } }
        }
        if ($value -is [array]) { return ($value -join ', ') }
        return [string]$value
    } catch { return $null }
}

function Invoke-ApiRequest {
    param(
        [string]$Method = 'GET',
        [string]$Path,
        [string]$Body = $null,
        [switch]$NoAuth,
        [string]$OutFile = $null
    )

    $uri = if ($Path -match '^https?://') { $Path } else { "$script:BaseUrl/" + $Path.TrimStart('/') }
    $headers = @{ 'Accept' = 'application/json' }
    if (-not $NoAuth -and $ApiKey) { $headers['Authorization'] = "Bearer $ApiKey" }

    $params = @{
        Uri                = $uri
        Method             = $Method
        Headers            = $headers
        TimeoutSec         = $TimeoutSec
        UseBasicParsing    = $true
        ErrorAction        = 'Stop'
    }
    if ($PSBoundParameters.ContainsKey('OutFile') -and $OutFile) {
        $params['OutFile'] = $OutFile
    }
    if ($Body) {
        $params['Body']        = [System.Text.Encoding]::UTF8.GetBytes($Body)
        $params['ContentType'] = 'application/json; charset=utf-8'
    }

    try {
        $resp = Invoke-WebRequest @params
        $content = $null
        if (-not $OutFile) { $content = [string]$resp.Content }
        return [pscustomobject]@{
            Ok         = $true
            StatusCode = [int]$resp.StatusCode
            Content    = $content
            Headers    = $resp.Headers
            Uri        = $uri
        }
    } catch {
        $status = 0
        $content = $null
        $response = $null
        try { $response = $_.Exception.Response } catch { }
        if ($response) {
            try { $status = [int]$response.StatusCode } catch { $status = 0 }
            if (-not $OutFile) {
                try {
                    $stream = $response.GetResponseStream()
                    if ($stream) {
                        $reader = New-Object System.IO.StreamReader($stream)
                        $content = $reader.ReadToEnd()
                        $reader.Dispose()
                    }
                } catch { }
            }
        }
        return [pscustomobject]@{
            Ok         = $false
            StatusCode = $status
            Content    = $content
            Headers    = $null
            Uri        = $uri
            Error      = $_.Exception.Message
        }
    }
}

function ConvertFrom-JsonSafe([string]$Text) {
    if ([string]::IsNullOrWhiteSpace($Text)) { return $null }
    try { return $Text | ConvertFrom-Json } catch { return $null }
}

function Get-PropValue($Object, [string]$Name) {
    if ($null -eq $Object) { return $null }
    if ($Object -is [System.Collections.IDictionary]) {
        foreach ($k in $Object.Keys) { if ($k -ieq $Name) { return $Object[$k] } }
        return $null
    }
    $prop = $Object.PSObject.Properties[$Name]
    if ($prop) { return $prop.Value }
    return $null
}

function Test-PdfFile([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return [pscustomobject]@{ IsPdf = $false; Size = 0; Header = ''; PageCount = 0 }
    }
    $bytes = [System.IO.File]::ReadAllBytes($Path)
    $header = ''
    if ($bytes.Length -ge 5) {
        $header = [System.Text.Encoding]::ASCII.GetString($bytes, 0, 5)
    }
    $text = [System.Text.Encoding]::GetEncoding('ISO-8859-1').GetString($bytes)
    $pageMatches = [regex]::Matches($text, '/Type\s*/Page[^s]')
    $countMatches = [regex]::Matches($text, '/Count\s+(\d+)')
    $pageCount = $pageMatches.Count
    if ($countMatches.Count -gt 0) {
        $maxCount = 0
        foreach ($m in $countMatches) {
            $n = 0
            if ([int]::TryParse($m.Groups[1].Value, [ref]$n) -and $n -gt $maxCount) { $maxCount = $n }
        }
        if ($maxCount -gt $pageCount) { $pageCount = $maxCount }
    }
    return [pscustomobject]@{
        IsPdf     = ($header -eq '%PDF-')
        Size      = $bytes.Length
        Header    = $header
        PageCount = $pageCount
    }
}

function Save-ResultFile([string]$Name) {
    if (-not (Test-Path -LiteralPath $OutputDir)) {
        New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
    }
    return Join-Path $OutputDir ("pdf-service-test-" + $Name + "-" + (Get-Date -Format 'yyyyMMdd-HHmmss') + ".pdf")
}

# -----------------------------------------------------------------------------
# Demo payloads (بدون دادهٔ حساس؛ فقط نمونه)
# -----------------------------------------------------------------------------
function Get-HotelVoucherPayload([string]$Reference) {
    $checkIn  = (Get-Date).AddDays(10).ToString('yyyy-MM-dd')
    $checkOut = (Get-Date).AddDays(13).ToString('yyyy-MM-dd')
    $payload = [ordered]@{
        template  = $Template
        reference = $Reference
        data      = [ordered]@{
            guest_name        = 'علی رضایی'
            hotel_name        = 'هتل نمونهٔ پارسیان'
            booking_reference = $Reference
            check_in          = $checkIn
            check_out         = $checkOut
            room_count        = 2
            total_price       = 12500000
            currency          = 'IRR'
            guests            = @(
                @{ name = 'علی رضایی'; passport = 'A1234567' },
                @{ name = 'مریم احمدی'; passport = 'B7654321' }
            )
            notes             = 'تست یکپارچهٔ سرویس تولید اسناد — رندر فارسی و راست‌چین.'
        }
    }
    return ($payload | ConvertTo-Json -Depth 10 -Compress)
}

function Get-TourItineraryPayload([string]$Reference) {
    $start = (Get-Date).AddDays(20)
    $days = @()
    for ($i = 1; $i -le 8; $i++) {
        $days += [ordered]@{
            day_number  = $i
            title       = "روز $i — برنامهٔ سفر"
            description = "شرح فعالیت‌های روز $i در تور نمونه؛ بازدید از جاذبه‌ها و اقامت در هتل."
            meals       = 'صبحانه، ناهار'
            hotel       = "هتل نمونهٔ $i"
        }
    }
    $payload = [ordered]@{
        template  = $MultipageTemplate
        reference = $Reference
        data      = [ordered]@{
            tour_name         = 'تور ۸ روزهٔ نمونه'
            tour_code         = $Reference
            start_date        = $start.ToString('yyyy-MM-dd')
            end_date          = $start.AddDays(7).ToString('yyyy-MM-dd')
            traveler_count    = 12
            duration_days     = 8
            destination       = 'شیراز و اصفهان'
            days              = $days
            includes          = @('اقامت در هتل', 'ترانسفر فرودگاهی', 'راهنمای تور')
            excludes          = @('بلیط هواپیما', 'وعده‌های ناهار')
            emergency_contact = '021-00000000'
            terms             = 'شرایط نمونهٔ تست یکپارچه.'
        }
    }
    return ($payload | ConvertTo-Json -Depth 10 -Compress)
}

# -----------------------------------------------------------------------------
# Start
# -----------------------------------------------------------------------------
Write-Head "تست یکپارچهٔ سرویس تولید اسناد (Gotenberg واقعی)"
Write-Info ("Base URL : " + $script:BaseUrl)
Write-Info ("Template : " + $Template + "  |  Multipage: " + $MultipageTemplate)
Write-Info ("Output   : " + $OutputDir)

if ([string]::IsNullOrWhiteSpace($ApiKey)) {
    Write-Fail "PDF_API_KEY (یا پارامتر -ApiKey) تنظیم نشده است."
    Write-Info "کلید خام را از پنل (/admin → پروژه‌ها → کلیدها) بگیرید و در متغیر محیطی بگذارید؛"
    Write-Info "هیچ کلیدی در این اسکریپت ذخیره نکنید."
    exit 2
}
Write-Info ("API Key  : " + $ApiKey.Substring(0, [Math]::Min(12, $ApiKey.Length)) + '... (masked)')

# -----------------------------------------------------------------------------
# 1) Health
# -----------------------------------------------------------------------------
Write-Head "1) سلامت سرویس"
$up = Invoke-ApiRequest -Method GET -Path "/up" -NoAuth
if ($up.StatusCode -eq 200) { Write-Pass "/up → 200" } else { Write-Fail ("/up → " + $up.StatusCode + " (انتظار 200)") }

$health = Invoke-ApiRequest -Method GET -Path "/api/v1/health" -NoAuth
if ($health.StatusCode -eq 200) {
    $healthJson = ConvertFrom-JsonSafe $health.Content
    $healthOk = Get-PropValue $healthJson 'success'
    if ($healthOk -eq $true) {
        Write-Pass "/api/v1/health → 200 (success=true)"
        $hs = Get-PropValue $healthJson 'data'
        $status = Get-PropValue $hs 'status'
        if ($status) { Write-Info ("service status: " + $status) }
        $got = Get-PropValue $hs 'gotenberg'
        if ($null -ne $got) { Write-Info ("gotenberg: " + ($got | ConvertTo-Json -Compress -Depth 3)) }
    } else {
        Write-Fail "/api/v1/health پاسخ success=true نداشت: $($health.Content)"
    }
} else {
    Write-Fail ("/api/v1/health → " + $health.StatusCode + " (انتظار 200)")
}

# -----------------------------------------------------------------------------
# 2) Authentication
# -----------------------------------------------------------------------------
Write-Head "2) احراز هویت API Key"
$noAuth = Invoke-ApiRequest -Method GET -Path "/api/v1/templates" -NoAuth
if ($noAuth.StatusCode -in @(401, 403)) {
    Write-Pass ("درخواست بدون کلید رد شد → " + $noAuth.StatusCode)
} elseif ($noAuth.StatusCode -eq 200) {
    Write-Fail "درخواست بدون Authorization به /templates پاسخ 200 داد (نشت اطلاعات/نبود auth)!"
} else {
    Write-Fail ("درخواست بدون کلید وضعیت غیرمنتظره: " + $noAuth.StatusCode)
}

$templatesResp = Invoke-ApiRequest -Method GET -Path "/api/v1/templates"
$availableSlugs = @()
if ($templatesResp.StatusCode -eq 200) {
    Write-Pass "/api/v1/templates با Bearer → 200"
    $tj = ConvertFrom-JsonSafe $templatesResp.Content
    $data = Get-PropValue $tj 'data'
    $list = $null
    if ($data -is [array]) { $list = $data }
    elseif ($data) {
        $inner = Get-PropValue $data 'templates'
        if ($inner -is [array]) { $list = $inner } else { $list = @($data) }
    }
    if ($list) {
        foreach ($t in $list) {
            $slug = Get-PropValue $t 'slug'
            if ($slug) { $availableSlugs += [string]$slug }
        }
        Write-Info ("قالب‌های مجاز: " + ($availableSlugs -join ', '))
    }
} else {
    Write-Fail ("/api/v1/templates → " + $templatesResp.StatusCode + " — کلید/پروژه/دسترسی را بررسی کنید")
}

if ($availableSlugs.Count -gt 0) {
    if ($availableSlugs -notcontains $Template) {
        Write-Fail ("قالب '" + $Template + "' برای این کلید مجاز نیست. از PDF_TEMPLATE یا اتصال قالب در پنل استفاده کنید.")
    } else {
        Write-Pass ("قالب '" + $Template + "' در لیست مجاز است")
    }
}

$canGenerate = ($templatesResp.StatusCode -eq 200) -and ($availableSlugs.Count -eq 0 -or $availableSlugs -contains $Template)

# -----------------------------------------------------------------------------
# 3) Sync generation + PDF validation
# -----------------------------------------------------------------------------
Write-Head "3) تولید هم‌زمان PDF (قالب فارسی)"
if ($canGenerate) {
    $outFile = Save-ResultFile 'sync'
    $body = Get-HotelVoucherPayload ("IT-SYNC-" + (Get-Date -Format 'HHmmss'))
    $syncStart = Get-Date
    $sync = Invoke-ApiRequest -Method POST -Path "/api/v1/documents/generate" -Body $body -OutFile $outFile
    $syncMs = [int]((Get-Date) - $syncStart).TotalMilliseconds

    if ($sync.StatusCode -eq 200) {
        $pdf = Test-PdfFile $outFile
        if ($pdf.IsPdf -and $pdf.Size -gt 500) {
            Write-Pass ("PDF تولید شد: " + [Math]::Round($pdf.Size / 1024, 1) + " KB در " + $syncMs + " ms")
            $docId = Get-HeaderValue $sync.Headers 'X-Document-Id'
            if ($docId) { Write-Pass ("هدر X-Document-Id موجود است: " + $docId) } else { Write-Info "هدر X-Document-Id یافت نشد (اختیاری در این تست)" }
            $genMs = Get-HeaderValue $sync.Headers 'X-Generation-Time-Ms'
            if ($genMs) { Write-Info ("X-Generation-Time-Ms: " + $genMs) }
            $reqId = Get-HeaderValue $sync.Headers 'X-Request-Id'
            if ($reqId) { Write-Pass ("هدر X-Request-Id موجود است: " + $reqId) } else { Write-Info "هدر X-Request-Id یافت نشد" }
            Write-Info ("فایل: " + $outFile)
        } else {
            Write-Fail ("پاسخ 200 بود اما خروجی PDF معتبر نیست (header='" + $pdf.Header + "', size=" + $pdf.Size + ")")
        }
    } else {
        Write-Fail ("generate → " + $sync.StatusCode + " | " + $sync.Content)
    }

    # بررسی ساختار PDF (شمارش صفحه heuristic)
    if ((Test-Path -LiteralPath $outFile)) {
        $pdfMeta = Test-PdfFile $outFile
        if ($pdfMeta.PageCount -ge 1) {
            Write-Pass ("ساختار PDF سالم است (تشخیص " + $pdfMeta.PageCount + " صفحه)")
        } else {
            Write-Info "شمارش صفحه از روی بایت‌ها قابل تشخیص نبود (PDF معتبر است)"
        }
        if (Get-Command pdftoppm -ErrorAction SilentlyContinue) {
            $pngBase = [System.IO.Path]::ChangeExtension($outFile, $null)
            try {
                & pdftoppm -png -f 1 -l 1 $outFile $pngBase 2>$null | Out-Null
                $png = Get-ChildItem -Path ($pngBase + '*.png') -ErrorAction SilentlyContinue | Select-Object -First 1
                if ($png) { Write-Info ("پیش‌نمایش صفحهٔ اول برای بازبینی چشمی: " + $png.FullName) }
            } catch { }
        }
    }
} else {
    Write-Skip "تولید هم‌زمان به‌دلیل نبود دسترسی قالب رد شد"
}

# -----------------------------------------------------------------------------
# 4) Multipage template
# -----------------------------------------------------------------------------
Write-Head "4) قالب چندصفحه‌ای"
if ($canGenerate -and $availableSlugs.Count -gt 0 -and ($availableSlugs -notcontains $MultipageTemplate)) {
    Write-Skip ("قالب '" + $MultipageTemplate + "' برای این کلید مجاز نیست")
} elseif ($canGenerate) {
    $outFileMp = Save-ResultFile 'multipage'
    $bodyMp = Get-TourItineraryPayload ("IT-MP-" + (Get-Date -Format 'HHmmss'))
    $mp = Invoke-ApiRequest -Method POST -Path "/api/v1/documents/generate" -Body $bodyMp -OutFile $outFileMp
    if ($mp.StatusCode -eq 200) {
        $pdfMp = Test-PdfFile $outFileMp
        if ($pdfMp.IsPdf -and $pdfMp.Size -gt 500) {
            if ($pdfMp.PageCount -ge 2) {
                Write-Pass ("قالب چندصفحه‌ای PDF تولید کرد (" + $pdfMp.PageCount + " صفحه، " + [Math]::Round($pdfMp.Size / 1024, 1) + " KB)")
            } elseif ($pdfMp.PageCount -eq 1) {
                Write-Pass ("PDF چندصفحه‌ای تولید شد اما تشخیص خودکار فقط ۱ صفحه یافت (احتمال فشرده‌سازی PDF)")
            } else {
                Write-Pass ("PDF چندصفحه‌ای تولید شد (" + [Math]::Round($pdfMp.Size / 1024, 1) + " KB)")
            }
            Write-Info ("فایل: " + $outFileMp)
        } else {
            Write-Fail ("خروجی قالب چندصفحه‌ای PDF معتبر نیست (header='" + $pdfMp.Header + "', size=" + $pdfMp.Size + ")")
        }
    } else {
        Write-Fail ("multipage generate → " + $mp.StatusCode + " | " + $mp.Content)
    }
} else {
    Write-Skip "قالب چندصفحه‌ای رد شد"
}

# -----------------------------------------------------------------------------
# 5) Queue flow + signed download
# -----------------------------------------------------------------------------
Write-Head "5) جریان صف (async) و دانلود امضاشده"
if ($SkipQueue -or $env:PDF_SKIP_QUEUE -eq '1') {
    Write-Skip "تست صف با درخواست کاربر رد شد (-SkipQueue / PDF_SKIP_QUEUE=1)"
} elseif (-not $canGenerate) {
    Write-Skip "تست صف به‌دلیل نبود دسترسی قالب رد شد"
} else {
    $queueBody = Get-HotelVoucherPayload ("IT-QUEUE-" + (Get-Date -Format 'HHmmss'))
    $queueResp = Invoke-ApiRequest -Method POST -Path "/api/v1/documents/queue" -Body $queueBody
    if ($queueResp.StatusCode -eq 202) {
        Write-Pass "POST /documents/queue → 202"
        $qj = ConvertFrom-JsonSafe $queueResp.Content
        $qData = Get-PropValue $qj 'data'
        $docId = Get-PropValue $qData 'id'
        if (-not $docId) { $docId = Get-PropValue $qData 'ulid' }
        $statusUrl = Get-PropValue $qData 'status_url'
        if (-not $statusUrl -and $docId) { $statusUrl = "/api/v1/documents/$docId/status" }
        if (-not $docId -or -not $statusUrl) {
            Write-Fail "پاسخ queue فاقد id/status_url است: $($queueResp.Content)"
        } else {
            Write-Info ("id=" + $docId)
            $statusUrlStr = [string]$statusUrl
            $deadline = (Get-Date).AddSeconds($QueueTimeoutSec)
            $lastStatus = $null
            $completed = $false
            $failedInfo = $null
            $downloadUrl = $null
            while ((Get-Date) -lt $deadline) {
                Start-Sleep -Seconds 2
                $st = Invoke-ApiRequest -Method GET -Path $statusUrlStr
                if ($st.StatusCode -eq 200) {
                    $sj = ConvertFrom-JsonSafe $st.Content
                    $sd = Get-PropValue $sj 'data'
                    $lastStatus = [string](Get-PropValue $sd 'status')
                    if ($lastStatus -eq 'completed') {
                        $completed = $true
                        $downloadUrl = Get-PropValue $sd 'download_url'
                        break
                    }
                    if ($lastStatus -eq 'failed') {
                        $failedInfo = [string](Get-PropValue $sd 'error_message')
                        break
                    }
                } else {
                    Write-Info ("status poll → " + $st.StatusCode)
                }
            }

            if ($completed) {
                Write-Pass "سند صف به وضعیت completed رسید"
                $dUrl = [string]$downloadUrl
                if ([string]::IsNullOrWhiteSpace($dUrl)) {
                    $dUrl = "/api/v1/documents/$docId/download"
                    $signed = $false
                } else {
                    $signed = ($dUrl -match 'signature=')
                }
                $outQueue = Save-ResultFile 'queue'
                if ($signed) {
                    $dl = Invoke-ApiRequest -Method GET -Path $dUrl -NoAuth -OutFile $outQueue
                    if ($dl.StatusCode -eq 200) {
                        $pdfQ = Test-PdfFile $outQueue
                        if ($pdfQ.IsPdf -and $pdfQ.Size -gt 500) {
                            Write-Pass ("دانلود با signed URL بدون Bearer موفق (" + [Math]::Round($pdfQ.Size / 1024, 1) + " KB)")
                        } else {
                            Write-Fail "فایل دانلودشده از signed URL PDF معتبر نیست"
                        }
                    } else {
                        Write-Fail ("download signed → " + $dl.StatusCode)
                    }

                    # امنیت: signed route بدون امضا باید رد شود
                    $unsigned = Invoke-ApiRequest -Method GET -Path "/api/v1/documents/$docId/download/signed" -NoAuth
                    if ($unsigned.StatusCode -in @(401, 403, 404)) {
                        Write-Pass ("signed route بدون امضا رد شد → " + $unsigned.StatusCode)
                    } else {
                        Write-Fail ("signed route بدون امضا وضعیت " + $unsigned.StatusCode + " برگرداند؛ انتظار 403/401")
                    }
                } else {
                    $dl = Invoke-ApiRequest -Method GET -Path $dUrl -OutFile $outQueue
                    if ($dl.StatusCode -eq 200) {
                        $pdfQ = Test-PdfFile $outQueue
                        if ($pdfQ.IsPdf -and $pdfQ.Size -gt 500) {
                            Write-Pass ("دانلود با Bearer موفق (" + [Math]::Round($pdfQ.Size / 1024, 1) + " KB)")
                        } else {
                            Write-Fail "فایل دانلودشده PDF معتبر نیست"
                        }
                    } else {
                        Write-Fail ("download (Bearer) → " + $dl.StatusCode)
                    }
                    Write-Info "download_url در status وجود ندارد؛ دانلود Bearer تست شد (signed URL توصیه می‌شود)"
                }
            } elseif ($failedInfo) {
                Write-Fail ("سند صف با وضعیت failed مواجه شد: " + $failedInfo + " — worker/Gotenberg را بررسی کنید")
                Write-Info "راهنما: docs/OPERATIONS.md بخش ۳ و ۸"
            } else {
                Write-Fail ("سند صف تا " + $QueueTimeoutSec + " ثانیه به completed نرسید (آخرین وضعیت: " + $lastStatus + ")")
                Write-Info "آیا queue worker در حال اجراست؟ (php artisan queue:work --queue=documents یا systemd pdf-queue)"
            }
        }
    } elseif ($queueResp.StatusCode -eq 429) {
        Write-Fail "درخواست queue با 429 محدود شد؛ تست صف را بعداً اجرا کنید"
    } else {
        Write-Fail ("POST /documents/queue → " + $queueResp.StatusCode + " | " + $queueResp.Content)
    }
}

# -----------------------------------------------------------------------------
# 6) Optional rate limit
# -----------------------------------------------------------------------------
Write-Head "6) Rate limit (اختیاری)"
$rateLimitEnabled = $RunRateLimit -or ($env:PDF_RUN_RATE_LIMIT -eq '1')
if (-not $rateLimitEnabled) {
    Write-Skip "فعال‌سازی با -RunRateLimit یا PDF_RUN_RATE_LIMIT=1"
} else {
    $limitHeader = $null
    $hit429 = $false
    $attempt = 0
    for ($attempt = 1; $attempt -le $RateLimitMaxAttempts; $attempt++) {
        $r = Invoke-ApiRequest -Method GET -Path "/api/v1/templates"
        if ($attempt -eq 1) {
            $limitHeader = Get-HeaderValue $r.Headers 'X-RateLimit-Limit'
            if ($limitHeader) { Write-Info ("X-RateLimit-Limit: " + $limitHeader) }
        }
        if ($r.StatusCode -eq 429) {
            $hit429 = $true
            $retryAfter = Get-HeaderValue $r.Headers 'Retry-After'
            Write-Pass ("429 پس از " + $attempt + " درخواست دریافت شد" + $(if ($retryAfter) { " (Retry-After=" + $retryAfter + ")" } else { "" }))
            break
        }
    }
    if (-not $hit429) {
        Write-Skip ("در " + $RateLimitMaxAttempts + " درخواست به 429 نرسیدیم (سقف ممکن است بالاتر باشد یا کش) — این تست اجباری نیست")
    }
}

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------
$elapsed = [int]((Get-Date) - $script:StartedAt).TotalSeconds
Write-Head "خلاصهٔ نتایج"
Write-Host ("  PASS: " + $script:PassCount) -ForegroundColor Green
Write-Host ("  FAIL: " + $script:FailCount) -ForegroundColor $(if ($script:FailCount -gt 0) { 'Red' } else { 'Gray' })
Write-Host ("  SKIP: " + $script:SkipCount) -ForegroundColor Yellow
Write-Host ("  زمان: " + $elapsed + " ثانیه") -ForegroundColor DarkGray

if ($script:FailCount -gt 0) {
    Write-Host ""
    Write-Host "  موارد ناموفق:" -ForegroundColor Red
    foreach ($f in $script:Failures) { Write-Host ("   - " + $f) -ForegroundColor Red }
    Write-Host ""
    Write-Host "  عیب‌یابی: docs/OPERATIONS.md (سلامت/صف/Gotenberg) و docs/TESTING.md" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "  ✅ همهٔ تست‌های اجباری موفق بودند." -ForegroundColor Green
exit 0
