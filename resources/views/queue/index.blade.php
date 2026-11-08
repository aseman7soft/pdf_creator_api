@extends('layouts.app')

@section('title', 'صف پردازش')
@section('heading', 'صف پردازش اسناد')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'صف پردازش']]])
@endsection

@section('content')
    @php $isAdmin = auth()->user()?->isAdmin() === true; @endphp

    <div class="page-heading">
        <h1>صف پردازش</h1>
        <span class="badge text-bg-light border">
            عمق صف (jobs): {{ $queueDepth === null ? 'نامشخص' : fa_num(number_format($queueDepth)) }}
        </span>
        <span class="badge {{ ($failedJobsCount ?? 0) > 0 ? 'text-bg-danger' : 'text-bg-light border' }}">
            ناموفق (failed_jobs): {{ $failedJobsCount === null ? 'نامشخص' : fa_num(number_format($failedJobsCount)) }}
        </span>
        @unless ($hasJobsTable)
            <span class="badge text-bg-warning">جدول jobs در دسترس نیست</span>
        @endunless
    </div>

    <div class="row g-3 mb-3">
        @foreach ($statuses as $value => $label)
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="stat-card__label mb-1">{{ $label }}</p>
                            <p class="stat-card__value mb-0">{{ fa_num(number_format($statusCounts[$value] ?? 0)) }}</p>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="{{ route('admin.queue.index', ['status' => $value]) }}">
                            <i class="bi bi-funnel"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.queue.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="شناسه Job، کد یا پیام خطا">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="status">وضعیت</label>
                <select class="form-select" id="status" name="status">
                    <option value="">همه</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
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
                <a href="{{ route('admin.queue.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header card-title">ردیابی Jobهای اسناد</div>
        <div class="table-responsive">
            @if ($jobs->isEmpty())
                <x-empty icon="bi-stack" title="Jobی یافت نشد" text="فیلترها را تغییر دهید یا سندی در صف قرار دهید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>سند</th>
                            <th>صف</th>
                            <th>وضعیت</th>
                            <th>تلاش</th>
                            <th>شروع</th>
                            <th>مدت</th>
                            <th>خطا</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr>
                                <td><x-fa :value="$job->id" /></td>
                                <td>
                                    @if ($job->document)
                                        <a class="ltr small" href="{{ route('admin.documents.show', $job->document) }}">
                                            {{ \Illuminate\Support\Str::limit($job->document->ulid, 14, '…') }}
                                        </a>
                                    @else
                                        <span class="text-secondary small">حذف‌شده</span>
                                    @endif
                                </td>
                                <td class="small">{{ $job->queue }}</td>
                                <td>
                                    <span class="badge {{ $job->status->badgeClass() }}">{{ $job->status->label() }}</span>
                                </td>
                                <td>{{ fa_num($job->attempts) }} / {{ fa_num($job->max_attempts) }}</td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$job->started_at" format="Y/m/d H:i:s" fallback="—" />
                                </td>
                                <td class="small">
                                    @if ($job->duration_ms !== null)
                                        {{ fa_num(number_format($job->duration_ms)) }} ms
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($job->error_code)
                                        <span class="badge text-bg-danger ltr">{{ $job->error_code }}</span>
                                        <div class="text-secondary">{{ \Illuminate\Support\Str::limit($job->error_message, 60) }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        @can('retry', $job)
                                            <form method="POST" action="{{ route('admin.queue.retry', $job) }}"
                                                  data-confirm="این Job برای اجرای مجدد در صف قرار می‌گیرد. ادامه می‌دهید؟">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="تلاش مجدد">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $job)
                                            <form method="POST" action="{{ route('admin.queue.destroy', $job) }}"
                                                  data-confirm="آیا از حذف این ردیف Job مطمئن هستید؟">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($jobs->hasPages())
            <div class="card-body border-top">{{ $jobs->links() }}</div>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">Jobهای در صف (جدول jobs)</span>
                    <span class="badge text-bg-light border">
                        {{ $queueDepth === null ? 'نامشخص' : fa_num(number_format($queueDepth)) }}
                    </span>
                </div>
                <div class="table-responsive">
                    @if ($databaseJobs->isEmpty())
                        <x-empty icon="bi-inboxes" title="صف خالی است" />
                    @else
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>صف</th>
                                    <th>تلاش</th>
                                    <th>زمان‌بندی</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($databaseJobs as $row)
                                    <tr>
                                        <td><x-fa :value="$row->id" /></td>
                                        <td class="small">{{ $row->queue }}</td>
                                        <td><x-fa :value="$row->attempts" /></td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="\Illuminate\Support\Carbon::createFromTimestamp((int) $row->available_at)" format="Y/m/d H:i" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">Jobهای ناموفق (failed_jobs)</span>
                    @if ($isAdmin && ($failedJobsCount ?? 0) > 0)
                        <form method="POST" action="{{ route('admin.queue.failed.flush') }}"
                              data-confirm="همه Jobهای ناموفق حذف می‌شوند. ادامه می‌دهید؟">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash3"></i> پاک‌سازی همه
                            </button>
                        </form>
                    @endif
                </div>
                <div class="table-responsive">
                    @if ($failedJobs->isEmpty())
                        <x-empty icon="bi-check2-circle" title="Job ناموفقی وجود ندارد" />
                    @else
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>UUID</th>
                                    <th>صف</th>
                                    <th>زمان</th>
                                    <th>خطا</th>
                                    @if ($isAdmin)
                                        <th class="text-end">عملیات</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($failedJobs as $row)
                                    <tr>
                                        <td class="small ltr">{{ \Illuminate\Support\Str::limit($row->uuid, 14, '…') }}</td>
                                        <td class="small">{{ $row->queue }}</td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$row->failed_at" format="Y/m/d H:i" />
                                        </td>
                                        <td class="small">
                                            <span class="text-danger">{{ \Illuminate\Support\Str::limit((string) $row->exception, 70) }}</span>
                                        </td>
                                        @if ($isAdmin)
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <form method="POST"
                                                          action="{{ route('admin.queue.failed.retry', $row->uuid) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                                                title="بازگرداندن به صف">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST"
                                                          action="{{ route('admin.queue.failed.forget', $row->uuid) }}"
                                                          data-confirm="این Job ناموفق حذف شود؟">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                title="حذف">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                @unless ($isAdmin)
                    <div class="card-footer bg-white">
                        <p class="hint-text mb-0">مدیریت Jobهای ناموفق فقط برای مدیر کل فعال است.</p>
                    </div>
                @endunless
            </div>
        </div>
    </div>
@endsection
