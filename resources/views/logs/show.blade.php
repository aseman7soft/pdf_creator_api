@extends('layouts.app')

@section('title', 'جزئیات لاگ درخواست')
@section('heading', 'جزئیات لاگ درخواست')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'لاگ درخواست‌ها', 'url' => route('admin.logs.index')],
            ['label' => \Illuminate\Support\Str::limit($log->request_id, 16, '…')],
        ],
    ])
@endsection

@section('content')
    <div class="page-heading">
        <h1 class="ltr">{{ $log->request_id }}</h1>
        <span class="badge {{ $log->statusBadgeClass() }} fs-6 ltr">{{ $log->status_code }}</span>
        <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-secondary ms-auto">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    @if ($log->error_code || $log->error_message)
        <div class="alert alert-danger">
            <strong class="d-block mb-1">خطا</strong>
            @if ($log->error_code)
                <span class="badge text-bg-dark ltr mb-1">{{ $log->error_code }}</span>
            @endif
            <div>{{ $log->error_message ?: '—' }}</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header card-title">اطلاعات درخواست</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>Request ID</dt>
                        <dd><code class="ltr">{{ $log->request_id }}</code></dd>
                        <dt>متد / مسیر</dt>
                        <dd class="ltr">{{ $log->method }} {{ $log->path }}</dd>
                        <dt>نام مسیر</dt>
                        <dd class="ltr">{{ $log->route_name ?: '—' }}</dd>
                        <dt>کد وضعیت</dt>
                        <dd><span class="badge {{ $log->statusBadgeClass() }} ltr">{{ $log->status_code }}</span></dd>
                        <dt>مدت اجرا</dt>
                        <dd>{{ fa_num(number_format($log->duration_ms)) }} میلی‌ثانیه</dd>
                        <dt>حجم درخواست / پاسخ</dt>
                        <dd>
                            {{ format_bytes($log->request_bytes) }} /
                            {{ format_bytes($log->response_bytes) }}
                        </dd>
                        <dt>آدرس IP</dt>
                        <dd class="ltr">{{ $log->ip_address ?: '—' }}</dd>
                        <dt>User Agent</dt>
                        <dd class="ltr small">{{ $log->user_agent ?: '—' }}</dd>
                        <dt>زمان ثبت</dt>
                        <dd><x-jdate :date="$log->created_at" format="Y/m/d H:i:s" /></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header card-title">موجودیت‌های مرتبط</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>پروژه</dt>
                        <dd>
                            @if ($log->project)
                                <a href="{{ route('admin.projects.show', $log->project) }}">{{ $log->project->name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>کلید API</dt>
                        <dd>
                            @if ($log->apiKey)
                                {{ $log->apiKey->name }} <code class="ltr">{{ $log->apiKey->maskedKey() }}</code>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>قالب</dt>
                        <dd>
                            @if ($log->template)
                                <a href="{{ route('admin.templates.edit', $log->template) }}">{{ $log->template->name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>سند</dt>
                        <dd>
                            @if ($log->document)
                                <a class="ltr" href="{{ route('admin.documents.show', $log->document) }}">
                                    {{ $log->document->ulid }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
