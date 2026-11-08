@extends('layouts.app')

@section('title', 'جزئیات سند')
@section('heading', 'جزئیات سند')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'اسناد', 'url' => route('admin.documents.index')],
            ['label' => \Illuminate\Support\Str::limit($document->ulid, 16, '…')],
        ],
    ])
@endsection

@section('content')
    @php $canManage = auth()->user()?->canManage() ?? false; @endphp

    <div class="page-heading">
        <h1 class="ltr">{{ $document->ulid }}</h1>
        <span class="badge {{ $document->status->badgeClass() }} fs-6">{{ $document->status->label() }}</span>
        @if ($document->reference)
            <span class="badge text-bg-light border">مرجع: <span class="ltr">{{ $document->reference }}</span></span>
        @endif

        <div class="ms-auto d-flex flex-wrap gap-2">
            @if ($document->canDownload())
                <a href="{{ route('admin.documents.download', $document) }}" class="btn btn-primary">
                    <i class="bi bi-download"></i> دانلود PDF
                </a>
            @endif
            @if ($canManage && $document->status === \App\Enums\DocumentStatus::Failed)
                <form method="POST" action="{{ route('admin.documents.retry', $document) }}"
                      data-confirm="تولید مجدد این سند در صف قرار می‌گیرد. ادامه می‌دهید؟">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-arrow-repeat"></i> تلاش مجدد
                    </button>
                </form>
            @endif
            @can('delete', $document)
                <form method="POST" action="{{ route('admin.documents.destroy', $document) }}"
                      data-confirm="آیا از حذف این سند و فایل PDF آن مطمئن هستید؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </form>
            @endcan
            <a href="{{ route('admin.documents.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    @if ($document->error_code || $document->error_message)
        <div class="alert alert-danger">
            <div class="d-flex gap-2">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                <div>
                    <strong class="d-block">خطای تولید سند</strong>
                    @if ($document->error_code)
                        <span class="badge text-bg-dark ltr mb-1">{{ $document->error_code }}</span>
                    @endif
                    <div>{{ $document->error_message ?: '—' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header card-title">مشخصات سند</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>شناسه عمومی (ULID)</dt>
                        <dd><code class="ltr">{{ $document->ulid }}</code></dd>
                        <dt>مرجع</dt>
                        <dd class="ltr">{{ $document->reference ?: '—' }}</dd>
                        <dt>قالب</dt>
                        <dd>
                            @if ($document->template)
                                <a href="{{ route('admin.templates.edit', $document->template) }}">
                                    {{ $document->template->name }}
                                </a>
                            @else
                                {{ $document->template_name ?: '—' }}
                            @endif
                            <span class="badge text-bg-light border">v{{ fa_num($document->template_version ?? '—') }}</span>
                        </dd>
                        <dt>پروژه</dt>
                        <dd>
                            @if ($document->project)
                                <a href="{{ route('admin.projects.show', $document->project) }}">
                                    {{ $document->project->name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>کلید API</dt>
                        <dd>
                            @if ($document->apiKey)
                                {{ $document->apiKey->name }}
                                <code class="ltr">{{ $document->apiKey->maskedKey() }}</code>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>حالت تولید</dt>
                        <dd>{{ $document->generation_mode?->label() ?? '—' }}</dd>
                        <dt>تاریخ ایجاد</dt>
                        <dd><x-jdate :date="$document->created_at" format="Y/m/d H:i:s" /></dd>
                        <dt>تاریخ تولید</dt>
                        <dd><x-jdate :date="$document->generated_at" format="Y/m/d H:i:s" fallback="—" /></dd>
                        <dt>انقضا</dt>
                        <dd><x-jdate :date="$document->expires_at" format="Y/m/d H:i" fallback="بدون انقضا" /></dd>
                        <dt>حجم فایل</dt>
                        <dd>{{ $document->humanFileSize() }}</dd>
                        <dt>تعداد صفحات</dt>
                        <dd><x-fa :value="$document->page_count" /></dd>
                        <dt>مسیر ذخیره</dt>
                        <dd><code class="ltr">{{ $document->file_path ?: '—' }}</code></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">لینک امضاشده دانلود</div>
                <div class="card-body">
                    <p class="hint-text">
                        این لینک موقت است و برای دانلود بدون احراز هویت API کاربرد دارد. آن را با احتیاط به اشتراک بگذارید.
                    </p>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control ltr" id="signed-url" readonly
                               value="{{ $signedUrl }}">
                        <button type="button" class="btn btn-outline-primary" data-copy-from="#signed-url">
                            <i class="bi bi-clipboard"></i> کپی
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card mb-3">
                <div class="card-header card-title">تلاش‌های اجرا (Jobs)</div>
                <div class="table-responsive">
                    @if ($document->documentJobs->isEmpty())
                        <x-empty icon="bi-stack" title="Jobی برای این سند ثبت نشده است"
                                 text="اسناد هم‌زمان (sync) بدون ردیف Job تولید می‌شوند." />
                    @else
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>وضعیت</th>
                                    <th>تلاش</th>
                                    <th>شروع</th>
                                    <th>پایان</th>
                                    <th>مدت</th>
                                    <th>خطا</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($document->documentJobs as $job)
                                    <tr>
                                        <td><x-fa :value="$job->id" /></td>
                                        <td>
                                            <span class="badge {{ $job->status->badgeClass() }}">{{ $job->status->label() }}</span>
                                        </td>
                                        <td>
                                            {{ fa_num($job->attempts) }} / {{ fa_num($job->max_attempts) }}
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$job->started_at" format="Y/m/d H:i:s" fallback="—" />
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$job->finished_at" format="Y/m/d H:i:s" fallback="—" />
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
                                                <div class="text-secondary">{{ $job->error_message }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header card-title">فراداده PDF</div>
                <div class="card-body">
                    @if (empty($document->pdf_meta))
                        <p class="hint-text mb-0">فراداده‌ای ثبت نشده است.</p>
                    @else
                        <dl class="kv-list mb-0 row">
                            @foreach ($document->pdf_meta as $key => $value)
                                <dt class="col-5">{{ $key }}</dt>
                                <dd class="col-7 ltr">
                                    {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                </dd>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">لاگ درخواست‌های مرتبط</div>
                <div class="table-responsive">
                    @if ($requestLogs->isEmpty())
                        <x-empty icon="bi-activity" title="درخواستی ثبت نشده است" />
                    @else
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>متد / مسیر</th>
                                    <th>وضعیت</th>
                                    <th>مدت</th>
                                    <th>زمان</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requestLogs as $log)
                                    <tr>
                                        <td>
                                            <a class="ltr small" href="{{ route('admin.logs.show', $log) }}">
                                                {{ \Illuminate\Support\Str::limit($log->request_id, 14, '…') }}
                                            </a>
                                        </td>
                                        <td class="small ltr">{{ $log->method }} {{ \Illuminate\Support\Str::limit($log->path, 30) }}</td>
                                        <td><span class="badge {{ $log->statusBadgeClass() }} ltr">{{ $log->status_code }}</span></td>
                                        <td class="small">{{ fa_num(number_format($log->duration_ms)) }} ms</td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$log->created_at" format="Y/m/d H:i:s" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
