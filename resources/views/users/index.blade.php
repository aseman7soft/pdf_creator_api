@extends('layouts.app')

@section('title', 'کاربران')
@section('heading', 'مدیریت کاربران')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'کاربران']]])
@endsection

@section('content')
    @php
        $currentUser = auth()->user();
        $isAdmin = $currentUser?->isAdmin() === true;
    @endphp

    <div class="page-heading">
        <h1>کاربران</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($users->total())) }} کاربر</span>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary ms-auto">
            <i class="bi bi-person-plus"></i> کاربر جدید
        </a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="نام یا ایمیل">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="role">نقش</label>
                <select class="form-select" id="role" name="role">
                    <option value="">همه</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="status">وضعیت</label>
                <select class="form-select" id="status" name="status">
                    <option value="">همه</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>فعال</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>غیرفعال</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> فیلتر
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($users->isEmpty())
                <x-empty icon="bi-people" title="کاربری یافت نشد" />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>نقش</th>
                            <th>وضعیت</th>
                            <th>آخرین ورود</th>
                            <th>تاریخ عضویت</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-circle">{{ $user->initials() }}</span>
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $user->name }}
                                                @if ($user->id === $currentUser?->id)
                                                    <span class="badge text-bg-info">شما</span>
                                                @endif
                                            </div>
                                            <span class="small text-secondary ltr">{{ $user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $user->isAdmin() ? 'text-bg-danger' : ($user->canManage() ? 'text-bg-primary' : 'text-bg-secondary') }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$user->last_login_at" format="Y/m/d H:i" fallback="هرگز" />
                                </td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$user->created_at" format="Y/m/d" />
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="btn btn-sm btn-outline-primary" title="ویرایش">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if ($user->id !== $currentUser?->id)
                                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}"
                                                  data-confirm="{{ $user->is_active ? 'کاربر غیرفعال شود؟' : 'کاربر فعال شود؟' }}">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                        title="{{ $user->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}">
                                                    <i class="bi {{ $user->is_active ? 'bi-person-dash' : 'bi-person-check' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                                  data-confirm="آیا از حذف کاربر «{{ $user->name }}» مطمئن هستید؟">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif

        @unless ($isAdmin)
            <div class="card-footer bg-white">
                <p class="hint-text mb-0">مدیریت کاربران فقط برای مدیر کل امکان‌پذیر است.</p>
            </div>
        @endunless
    </div>
@endsection
