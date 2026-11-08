@php
    $currentUser = auth()->user();
    $heading = trim($__env->yieldContent('heading'));
@endphp

<header class="panel-topbar">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-sidebar-toggle
            data-bs-toggle="tooltip" title="جمع/باز کردن منو" aria-label="منوی کناری">
        <i class="bi bi-list"></i>
    </button>

    <h2 class="panel-topbar__title">{{ $heading !== '' ? $heading : 'پنل مدیریت' }}</h2>

    <div class="panel-topbar__actions">
        <span class="d-none d-md-inline-flex align-items-center gap-1 text-secondary small">
            <i class="bi bi-calendar3"></i>
            <x-jdate :date="now()" format="l j F Y" />
        </span>

        <div class="dropdown">
            <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar-circle">{{ $currentUser?->initials() }}</span>
                <span class="d-none d-sm-inline">{{ $currentUser?->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2">
                    <div class="fw-bold small">{{ $currentUser?->name }}</div>
                    <div class="text-secondary small ltr">{{ $currentUser?->email }}</div>
                    <div class="mt-1">
                        <span class="badge text-bg-light border">{{ $currentUser?->role?->label() }}</span>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <span class="dropdown-item-text small text-secondary">
                        آخرین ورود:
                        @if ($currentUser?->last_login_at)
                            <x-jdate :date="$currentUser->last_login_at" format="Y/m/d H:i" />
                        @else
                            هرگز
                        @endif
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-left"></i>
                            خروج از حساب
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
