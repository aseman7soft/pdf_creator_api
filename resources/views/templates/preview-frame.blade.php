@php
    $appName = (string) setting('general.app_name', config('app.name', 'سرویس مرکزی تولید اسناد'));
    $useVite = file_exists(public_path('build/manifest.json'));
@endphp
@if (! empty($warning))
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>پیش‌نمایش قالب | {{ $appName }}</title>
        @if ($useVite)
            @vite(['resources/css/app.css'])
        @else
            <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
            <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
            <link rel="stylesheet" href="{{ asset('vendor/panel/admin.css') }}">
            <style>
                @font-face {
                    font-family: 'Vazirmatn';
                    src: url('{{ asset('vendor/fonts/Vazirmatn-Regular.woff2') }}') format('woff2');
                    font-weight: 400;
                    font-display: swap;
                }
            </style>
        @endif
    </head>
    <body class="bg-light">
        <div class="container py-4">
            <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div>
                    <strong class="d-block">پیش‌نمایش HTML در دسترس نیست</strong>
                    <span>{{ $warning }}</span>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">سورس قالب (فقط خواندنی)</div>
                <div class="card-body">
                    <div class="log-viewer" style="max-height: 60vh">{{ $source }}</div>
                </div>
            </div>
        </div>
    </body>
    </html>
@else
    {!! $html !!}
@endif
