@extends('layouts.app')

@section('title', 'لاگ برنامه')
@section('heading', 'لاگ برنامه (laravel.log)')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [['label' => 'لاگ برنامه']])
@endsection

@section('content')
    @php
        $levelClasses = [
            'ERROR' => 'log-line--error',
            'CRITICAL' => 'log-line--error',
            'ALERT' => 'log-line--error',
            'EMERGENCY' => 'log-line--error',
            'WARNING' => 'log-line--warning',
            'NOTICE' => 'log-line--notice',
            'DEBUG' => 'log-line--debug',
        ];
    @endphp

    <div class="page-heading">
        <h1>لاگ برنامه</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format(count($entries))) }} ورودی نمایش‌داده‌شده</span>
        <span class="badge text-bg-light border">حجم فایل: {{ format_bytes($fileSize) }}</span>
        <span class="badge text-bg-warning">حداکثر {{ fa_num(number_format($maxLines)) }} خط</span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p class="hint-text mb-2">
                فایل: <code class="ltr">{{ $path }}</code>
                — فقط انتهای فایل (حداکثر ۲ مگابایت آخر) خوانده می‌شود.
            </p>

            @unless ($exists)
                <div class="alert alert-warning mb-0">فایل لاگ هنوز ساخته نشده است.</div>
            @endunless

            <form method="GET" action="{{ route('admin.logs.app') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="level">سطح</label>
                    <select class="form-select" id="level" name="level">
                        <option value="">همه سطوح</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level }}" @selected(strtoupper((string) ($filters['level'] ?? '')) === $level)>
                                {{ $level }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="search">جستجو در متن لاگ</label>
                    <input type="search" class="form-control" id="search" name="search"
                           value="{{ $filters['search'] ?? '' }}" placeholder="مثلاً Gotenberg یا Exception">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> اعمال فیلتر
                    </button>
                    <a href="{{ route('admin.logs.app') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="card-title">خطوط لاگ</span>
            <span class="hint-text">
                کل ورودی‌های فایل: {{ fa_num(number_format($totalEntries)) }}
            </span>
        </div>
        <div class="card-body">
            @if (empty($entries))
                <x-empty icon="bi-terminal" title="ورودی لاگی برای نمایش نیست" />
            @else
                <div class="log-viewer">
                    @foreach ($entries as $entry)
                        @foreach ($entry['lines'] as $line)
                            <div class="{{ $levelClasses[$entry['level']] ?? '' }}">{{ $line }}</div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
