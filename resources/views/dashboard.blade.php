@extends('layouts.app')

@section('title', 'داشبورد')
@section('heading', 'داشبورد مدیریت')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'داشبورد']]])
@endsection

@php
    $cards = [
        [
            'label' => 'کل اسناد تولیدشده',
            'value' => fa_num(number_format((int) $stats['documents_total'])),
            'icon' => 'bi-file-earmark-pdf',
            'class' => 'text-bg-primary',
        ],
        [
            'label' => 'اسناد امروز',
            'value' => fa_num(number_format((int) $stats['documents_today'])),
            'icon' => 'bi-calendar-day',
            'class' => 'text-bg-info',
        ],
        [
            'label' => 'کل درخواست‌های API',
            'value' => fa_num(number_format((int) $stats['requests_total'])),
            'icon' => 'bi-activity',
            'class' => 'text-bg-dark',
        ],
        [
            'label' => 'درخواست‌های موفق',
            'value' => fa_num(number_format((int) $stats['requests_successful'])),
            'icon' => 'bi-check-circle',
            'class' => 'text-bg-success',
        ],
        [
            'label' => 'درخواست‌های ناموفق',
            'value' => fa_num(number_format((int) $stats['requests_failed'])),
            'icon' => 'bi-x-octagon',
            'class' => 'text-bg-danger',
        ],
        [
            'label' => 'پروژه‌های فعال',
            'value' => fa_num(number_format((int) $stats['projects_active'])),
            'icon' => 'bi-diagram-3',
            'class' => 'text-bg-warning',
        ],
        [
            'label' => 'قالب‌های فعال',
            'value' => fa_num(number_format((int) $stats['templates_active'])),
            'icon' => 'bi-filetype-html',
            'class' => 'text-bg-secondary',
        ],
        [
            'label' => 'میانگین زمان تولید',
            'value' => ((float) $stats['avg_generation_ms']) > 0
                ? fa_num(number_format((float) $stats['avg_generation_ms'] / 1000, 2)).' ثانیه'
                : '—',
            'icon' => 'bi-stopwatch',
            'class' => 'text-bg-primary',
        ],
    ];

    $successRate = (int) $stats['requests_total'] > 0
        ? round(((int) $stats['requests_successful'] / (int) $stats['requests_total']) * 100, 1)
        : 0;
@endphp

@section('content')
    <div class="row g-3 mb-3">
        @foreach ($cards as $card)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-card__icon {{ $card['class'] }} bg-opacity-10">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="stat-card__label">{{ $card['label'] }}</p>
                        <p class="stat-card__value">{{ $card['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">حجم تولید ۷ روز گذشته</span>
                    <span class="badge text-bg-light border">
                        نرخ موفقیت: {{ fa_num(number_format($successRate, 1)) }}٪
                    </span>
                </div>
                <div class="card-body">
                    <div class="bar-chart">
                        @foreach ($chart as $point)
                            <div class="bar-chart__col" title="{{ $point['label'] }}: {{ $point['count'] }}">
                                <div class="bar-chart__bar"
                                     style="height: {{ max(3, (int) $point['height']) }}%"></div>
                                <span class="bar-chart__label">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-secondary mt-2">
                        <span>حداکثر در بازه: {{ fa_num(number_format(max(array_column($chart, 'count')))) }} سند</span>
                        <span>جمع بازه: {{ fa_num(number_format(array_sum(array_column($chart, 'count')))) }} سند</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-header card-title">سلامت صف</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>Jobهای در انتظار (jobs)</dt>
                        <dd><x-fa :value="$queue['jobs']" /></dd>
                        <dt>Jobهای ناموفق (failed_jobs)</dt>
                        <dd>
                            <x-fa :value="$queue['failed_jobs']" />
                            @if (($queue['failed_jobs'] ?? 0) > 0)
                                <a href="{{ route('admin.queue.index') }}" class="badge text-bg-danger ms-1">بررسی</a>
                            @endif
                        </dd>
                        <dt>Document Job در انتظار</dt>
                        <dd><x-fa :value="$queue['pending']" /></dd>
                        <dt>در حال اجرا</dt>
                        <dd><x-fa :value="$queue['processing']" /></dd>
                        <dt>ناموفق</dt>
                        <dd><x-fa :value="$queue['failed']" /></dd>
                        <dt>تکمیل‌شده امروز</dt>
                        <dd><x-fa :value="$queue['completed_today']" /></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">وضعیت Gotenberg</span>
                    @if ($gotenberg['available'])
                        <span class="badge text-bg-success">در دسترس</span>
                    @else
                        <span class="badge text-bg-danger">در دسترس نیست</span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="mb-2">{{ $gotenberg['message'] }}</p>
                    <p class="hint-text mb-3">
                        آدرس: <code class="ltr">{{ config('pdf.gotenberg.url') }}</code>
                    </p>

                    @if (! empty($gotenberg['details']))
                        <dl class="kv-list mb-0">
                            @foreach ($gotenberg['details'] as $key => $value)
                                @if (is_scalar($value) && ! is_bool($value))
                                    <dt>{{ $key }}</dt>
                                    <dd class="ltr">{{ $value }}</dd>
                                @endif
                            @endforeach
                        </dl>
                    @endif

                    @can('viewAny', App\Models\Setting::class)
                        <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-outline-primary mt-3">
                            <i class="bi bi-gear"></i>
                            تنظیمات و تست اتصال
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">آخرین اسناد</span>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.documents.index') }}">
                        همه اسناد
                    </a>
                </div>
                <div class="table-responsive">
                    @if ($recentDocuments->isEmpty())
                        <x-empty icon="bi-file-earmark-x" title="هنوز سندی تولید نشده است" />
                    @else
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>قالب</th>
                                    <th>پروژه</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentDocuments as $document)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.documents.show', $document) }}" class="ltr">
                                                {{ \Illuminate\Support\Str::limit($document->ulid, 12, '…') }}
                                            </a>
                                        </td>
                                        <td>{{ $document->template?->name ?? $document->template_name ?? '—' }}</td>
                                        <td>{{ $document->project?->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $document->status->badgeClass() }}">
                                                {{ $document->status->label() }}
                                            </span>
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$document->created_at" format="Y/m/d H:i" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">آخرین درخواست‌های API</span>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.logs.index') }}">
                        همه لاگ‌ها
                    </a>
                </div>
                <div class="table-responsive">
                    @if ($recentLogs->isEmpty())
                        <x-empty icon="bi-activity" title="درخواستی ثبت نشده است" />
                    @else
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>مسیر</th>
                                    <th>وضعیت</th>
                                    <th>زمان</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentLogs as $log)
                                    <tr>
                                        <td class="small">
                                            <span class="ltr d-block">{{ $log->method }} {{ \Illuminate\Support\Str::limit($log->path, 34) }}</span>
                                            <span class="text-secondary">{{ $log->project?->name ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->statusBadgeClass() }} ltr">
                                                {{ $log->status_code }}
                                            </span>
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$log->created_at" format="H:i:s" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">پرتولیدترین قالب‌ها</div>
                <div class="card-body">
                    @if ($topTemplates->isEmpty())
                        <p class="hint-text mb-0">داده‌ای برای نمایش وجود ندارد.</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach ($topTemplates as $item)
                                <li class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                    <span>
                                        <i class="bi bi-filetype-html text-primary"></i>
                                        {{ $item['template']->name }}
                                    </span>
                                    <span class="badge text-bg-light border">
                                        {{ fa_num(number_format($item['total'])) }} سند
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
