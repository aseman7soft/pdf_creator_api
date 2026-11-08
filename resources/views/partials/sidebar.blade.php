@php
    $currentUser = auth()->user();
    $appName = (string) setting('general.app_name', config('app.name', 'سرویس مرکزی تولید اسناد'));
    $isAdmin = $currentUser?->isAdmin() === true;
@endphp

<aside class="panel-sidebar" id="panelSidebar">
    <a href="{{ route('admin.dashboard') }}" class="panel-sidebar__brand">
        <span class="brand-icon"><i class="bi bi-file-earmark-pdf"></i></span>
        <span>
            {{ $appName }}
            <small>پنل مدیریت</small>
        </span>
    </a>

    <nav class="panel-nav">
        <span class="panel-nav__section">نمای کلی</span>
        <a class="panel-nav__link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
           href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i>
            <span>داشبورد</span>
        </a>

        <span class="panel-nav__section">محتوا</span>
        <a class="panel-nav__link {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}"
           href="{{ route('admin.templates.index') }}">
            <i class="bi bi-filetype-html"></i>
            <span>قالب‌ها</span>
        </a>
        <a class="panel-nav__link {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}"
           href="{{ route('admin.assets.index') }}">
            <i class="bi bi-images"></i>
            <span>فایل‌ها و فونت‌ها</span>
        </a>

        <span class="panel-nav__section">سرویس API</span>
        <a class="panel-nav__link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}"
           href="{{ route('admin.projects.index') }}">
            <i class="bi bi-diagram-3"></i>
            <span>پروژه‌ها و کلیدها</span>
        </a>
        <a class="panel-nav__link {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}"
           href="{{ route('admin.documents.index') }}">
            <i class="bi bi-file-earmark-pdf"></i>
            <span>اسناد تولیدشده</span>
        </a>
        <a class="panel-nav__link {{ request()->routeIs('admin.queue.*') ? 'active' : '' }}"
           href="{{ route('admin.queue.index') }}">
            <i class="bi bi-stack"></i>
            <span>صف پردازش</span>
        </a>
        <a class="panel-nav__link {{ request()->routeIs('admin.logs.index') || request()->routeIs('admin.logs.show') ? 'active' : '' }}"
           href="{{ route('admin.logs.index') }}">
            <i class="bi bi-activity"></i>
            <span>لاگ درخواست‌های API</span>
        </a>

        @if ($isAdmin)
            <span class="panel-nav__section">مدیریت سیستم</span>
            <a class="panel-nav__link {{ request()->routeIs('admin.logs.app') ? 'active' : '' }}"
               href="{{ route('admin.logs.app') }}">
                <i class="bi bi-terminal"></i>
                <span>لاگ برنامه</span>
            </a>
            <a class="panel-nav__link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
               href="{{ route('admin.users.index') }}">
                <i class="bi bi-people"></i>
                <span>کاربران</span>
            </a>
            <a class="panel-nav__link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
               href="{{ route('admin.settings.index') }}">
                <i class="bi bi-gear"></i>
                <span>تنظیمات</span>
            </a>
        @endif

        <span class="panel-nav__section">راهنما</span>
        <a class="panel-nav__link" href="{{ url('/health') }}" target="_blank" rel="noopener">
            <i class="bi bi-heart-pulse"></i>
            <span>سلامت سرویس</span>
        </a>
    </nav>
</aside>
