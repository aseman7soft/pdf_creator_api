@extends('layouts.app')

@section('title', 'مدیریت قالب‌ها')
@section('heading', 'مدیریت قالب‌ها')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'قالب‌ها']]])
@endsection

@php
    $canCreate = auth()->user()?->can('create', App\Models\Template::class) ?? false;
    $canManage = auth()->user()?->canManage() ?? false;
@endphp

@section('content')
    <div class="page-heading">
        <h1>قالب‌ها</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($templates->total())) }} قالب</span>

        @if ($canCreate)
            <a href="{{ route('admin.templates.create') }}" class="btn btn-primary ms-auto">
                <i class="bi bi-plus-lg"></i>
                قالب جدید
            </a>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.templates.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="نام، اسلاگ، دسته یا توضیحات">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="category">دسته‌بندی</label>
                <select class="form-select" id="category" name="category">
                    <option value="">همه</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>
                            {{ $category }}
                        </option>
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
            <div class="col-6 col-md-2">
                <label class="form-label" for="per_page">تعداد در صفحه</label>
                <select class="form-select" id="per_page" name="per_page">
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 20) === $size)>
                            {{ fa_num($size) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i>
                    فیلتر
                </button>
                <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($templates->isEmpty())
                <x-empty icon="bi-filetype-html" title="قالبی یافت نشد"
                         text="با دکمه «قالب جدید» اولین قالب را بسازید یا فیلترها را تغییر دهید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>نام / اسلاگ</th>
                            <th>دسته</th>
                            <th>نسخه</th>
                            <th>متغیرها</th>
                            <th>اسناد</th>
                            <th>وضعیت</th>
                            <th>آخرین به‌روزرسانی</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        @if ($canManage)
                                            <a href="{{ route('admin.templates.edit', $template) }}">{{ $template->name }}</a>
                                        @else
                                            {{ $template->name }}
                                        @endif
                                    </div>
                                    <code>{{ $template->slug }}</code>
                                </td>
                                <td><span class="badge text-bg-light border">{{ $template->category }}</span></td>
                                <td><x-fa :value="$template->version" /></td>
                                <td><x-fa :value="$template->variables_count" /></td>
                                <td><x-fa :value="$template->documents_count" /></td>
                                <td>
                                    <span class="badge {{ $template->statusBadgeClass() }}">
                                        {{ $template->statusLabel() }}
                                    </span>
                                    <span class="badge text-bg-light border text-uppercase">{{ $template->locale }}</span>
                                </td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$template->updated_at" format="Y/m/d H:i" />
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.templates.preview', $template) }}"
                                           class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip"
                                           title="پیش‌نمایش">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.templates.versions', $template) }}"
                                           class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip"
                                           title="نسخه‌ها">
                                            <i class="bi bi-clock-history"></i>
                                        </a>

                                        @if ($canManage)
                                            <a href="{{ route('admin.templates.edit', $template) }}"
                                               class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip"
                                               title="ویرایش">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#copyModal{{ $template->id }}"
                                                    title="کپی قالب">
                                                <i class="bi bi-files"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.templates.toggle', $template) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm {{ $template->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                        data-bs-toggle="tooltip"
                                                        title="{{ $template->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}">
                                                    <i class="bi {{ $template->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.templates.destroy', $template) }}"
                                                  data-confirm="آیا از حذف قالب «{{ $template->name }}» مطمئن هستید؟ این عملیات قابل بازگشت نیست.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="tooltip" title="حذف">
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

        @if ($templates->hasPages())
            <div class="card-body border-top">
                {{ $templates->links() }}
            </div>
        @endif
    </div>

    @if ($canManage)
        @foreach ($templates as $template)
            <div class="modal fade" id="copyModal{{ $template->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('admin.templates.copy', $template) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">کپی قالب «{{ $template->name }}»</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="copyName{{ $template->id }}">نام قالب جدید</label>
                                <input type="text" class="form-control" id="copyName{{ $template->id }}"
                                       name="name" value="{{ $template->name }} (کپی)" required maxlength="150">
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="copySlug{{ $template->id }}">اسلاگ جدید</label>
                                <input type="text" class="form-control ltr" id="copySlug{{ $template->id }}"
                                       name="slug" value="{{ $template->slug }}-copy" required maxlength="150"
                                       pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                                <div class="form-text">حروف کوچک انگلیسی، اعداد و خط تیره.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-files"></i>
                                ایجاد کپی
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
