@extends('layouts.app')

@section('title', 'لاگ درخواست‌های API')
@section('heading', 'لاگ درخواست‌های API')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'لاگ درخواست‌ها']]])
@endsection

@section('content')
    @php $isAdmin = auth()->user()?->isAdmin() === true; @endphp

    <div class="page-heading">
        <h1>لاگ درخواست‌های API</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($logs->total())) }} رکورد</span>
        <span class="badge text-bg-light border">کل رکوردها: {{ fa_num(number_format($totalCount)) }}</span>
    </div>

    @if ($isAdmin)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.logs.purge') }}" class="row g-2 align-items-end"
                      data-confirm="رکوردهای قدیمی‌تر از تعداد روز مشخص‌شده برای همیشه حذف می‌شوند. ادامه می‌دهید؟">
                    @csrf
                    @method('DELETE')
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="days">پاک‌سازی رکوردهای قدیمی‌تر از (روز)</label>
                        <input type="number" class="form-control ltr" id="days" name="days" min="1" max="3650"
                               value="{{ old('days', 90) }}" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash3"></i> پاک‌سازی لاگ‌ها
                        </button>
                    </div>
                    <div class="col-12 col-md-6">
                        <p class="hint-text mb-0">
                            <i class="bi bi-shield-exclamation"></i>
                            این عملیات غیرقابل بازگشت است و فقط برای مدیر کل فعال می‌باشد.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.logs.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="request_id، مسیر یا کد خطا">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="project_id">پروژه</label>
                <select class="form-select" id="project_id" name="project_id">
                    <option value="">همه</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) ($filters['project_id'] ?? 0) === $project->id)>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="status_class">کلاس وضعیت</label>
                <select class="form-select" id="status_class" name="status_class">
                    <option value="">همه</option>
                    @foreach (['2xx' => 'موفق (2xx)', '3xx' => 'تغییر مسیر (3xx)', '4xx' => 'خطای کلاینت (4xx)', '5xx' => 'خطای سرور (5xx)'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status_class'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="method">متد</label>
                <select class="form-select" id="method" name="method">
                    <option value="">همه</option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                        <option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_from">از تاریخ</label>
                <input type="date" class="form-control ltr" id="date_from" name="date_from" dir="ltr"
                       value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_to">تا تاریخ</label>
                <input type="date" class="form-control ltr" id="date_to" name="date_to" dir="ltr"
                       value="{{ $filters['date_to'] ?? '' }}">
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
                <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($logs->isEmpty())
                <x-empty icon="bi-activity" title="رکوردی یافت نشد" text="با تغییر فیلترها دوباره تلاش کنید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>زمان</th>
                            <th>متد / مسیر</th>
                            <th>پروژه</th>
                            <th>وضعیت</th>
                            <th>مدت</th>
                            <th>Request ID</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="small text-secondary">
                                    <x-jdate :date="$log->created_at" format="Y/m/d H:i:s" />
                                </td>
                                <td>
                                    <span class="badge text-bg-light border ltr">{{ $log->method }}</span>
                                    <span class="ltr small">{{ \Illuminate\Support\Str::limit($log->path, 42) }}</span>
                                </td>
                                <td class="small">{{ $log->project?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $log->statusBadgeClass() }} ltr">{{ $log->status_code }}</span>
                                </td>
                                <td class="small">{{ fa_num(number_format($log->duration_ms)) }} ms</td>
                                <td class="small ltr">{{ \Illuminate\Support\Str::limit($log->request_id, 16, '…') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.logs.show', $log) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i> جزئیات
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($logs->hasPages())
            <div class="card-body border-top">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
