@extends('layouts.app')

@section('title', 'پروژه‌های API')
@section('heading', 'پروژه‌های API')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'پروژه‌های API']]])
@endsection

@section('content')
    @php $canManage = auth()->user()?->canManage() ?? false; @endphp

    <div class="page-heading">
        <h1>پروژه‌ها و کلیدها</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($projects->total())) }} پروژه</span>

        @if ($canManage)
            <a href="{{ route('admin.projects.create') }}" class="btn btn-primary ms-auto">
                <i class="bi bi-plus-lg"></i>
                پروژه جدید
            </a>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.projects.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="نام، کد یا توضیحات">
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
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> فیلتر
                </button>
                <a href="{{ route('admin.projects.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($projects->isEmpty())
                <x-empty icon="bi-diagram-3" title="پروژه‌ای یافت نشد"
                         text="برای دسترسی به API ابتدا یک پروژه بسازید و سپس کلید صادر کنید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>نام / کد</th>
                            <th>قالب‌های مجاز</th>
                            <th>کلیدها</th>
                            <th>اسناد</th>
                            <th>سقف نرخ</th>
                            <th>سقف ماهانه</th>
                            <th>وضعیت</th>
                            <th>آخرین فعالیت</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="{{ route('admin.projects.show', $project) }}">
                                        {{ $project->name }}
                                    </a>
                                    <div><code>{{ $project->code }}</code></div>
                                </td>
                                <td>
                                    @if (($project->templates_count ?? 0) === 0)
                                        <span class="badge text-bg-light border">همه قالب‌های فعال</span>
                                    @else
                                        <span class="badge text-bg-info">{{ fa_num($project->templates_count) }} قالب</span>
                                    @endif
                                </td>
                                <td><x-fa :value="$project->api_keys_count" /></td>
                                <td><x-fa :value="$project->documents_count" /></td>
                                <td>
                                    <x-fa :value="$project->rate_limit_per_minute" fallback="پیش‌فرض" />
                                </td>
                                <td><x-fa :value="$project->monthly_quota" fallback="نامحدود" /></td>
                                <td>
                                    <span class="badge {{ $project->statusBadgeClass() }}">
                                        {{ $project->statusLabel() }}
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$project->last_activity_at" format="Y/m/d H:i" fallback="—" />
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.projects.show', $project) }}"
                                           class="btn btn-sm btn-outline-secondary" title="جزئیات و کلیدها">
                                            <i class="bi bi-key"></i>
                                        </a>
                                        @if ($canManage)
                                            <a href="{{ route('admin.projects.edit', $project) }}"
                                               class="btn btn-sm btn-outline-primary" title="ویرایش">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.projects.toggle', $project) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm {{ $project->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                        title="{{ $project->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}">
                                                    <i class="bi {{ $project->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                                                  data-confirm="آیا از حذف پروژه «{{ $project->name }}» مطمئن هستید؟">
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

        @if ($projects->hasPages())
            <div class="card-body border-top">{{ $projects->links() }}</div>
        @endif
    </div>
@endsection
