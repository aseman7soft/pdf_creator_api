@php
    $appName = (string) setting('general.app_name', config('app.name', 'سرویس مرکزی تولید اسناد'));
    $brandColor = (string) setting('general.brand_color', '#0d6efd');
    $currentUser = auth()->user();
    $useVite = file_exists(public_path('build/manifest.json'));
    $supportEmail = setting('general.support_email');
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'داشبورد') | {{ $appName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @if ($useVite)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Non-Vite fallback: locally served Bootstrap RTL, icons, font and theme. --}}
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/panel/admin.css') }}">
        <style>
            @font-face {
                font-family: 'Vazirmatn';
                src: url('{{ asset('vendor/fonts/Vazirmatn-Regular.woff2') }}') format('woff2');
                font-weight: 400;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Vazirmatn';
                src: url('{{ asset('vendor/fonts/Vazirmatn-Bold.woff2') }}') format('woff2');
                font-weight: 700;
                font-style: normal;
                font-display: swap;
            }
        </style>
    @endif

    <style>
        :root {
            --panel-brand: {{ $brandColor }};
        }

        .btn-primary {
            --bs-btn-bg: var(--panel-brand);
            --bs-btn-border-color: var(--panel-brand);
            --bs-btn-hover-bg: var(--panel-brand);
            --bs-btn-hover-border-color: var(--panel-brand);
            --bs-btn-active-bg: var(--panel-brand);
            --bs-btn-active-border-color: var(--panel-brand);
            --bs-btn-disabled-bg: var(--panel-brand);
            --bs-btn-disabled-border-color: var(--panel-brand);
        }

        .panel-nav__link.active {
            background: linear-gradient(90deg, var(--panel-brand), color-mix(in srgb, var(--panel-brand) 60%, #000 20%));
        }

        .login-card__logo {
            background: linear-gradient(135deg, var(--panel-brand), #0ea5e9);
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="panel-shell">
        @include('partials.sidebar')

        <div class="panel-backdrop" data-sidebar-backdrop></div>

        <div class="panel-main">
            @include('partials.topbar')

            <main class="panel-content">
                @yield('breadcrumbs')
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    @unless ($useVite)
        <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('vendor/panel/panel.js') }}"></script>
    @endunless

    @stack('scripts')
</body>
</html>
