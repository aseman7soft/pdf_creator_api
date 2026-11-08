@extends('layouts.app')

@section('title', 'نسخه '.fa_num($version->version))
@section('heading', 'نسخه '.fa_num($version->version).' — '.$template->name)

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'قالب‌ها', 'url' => route('admin.templates.index')],
            ['label' => $template->name, 'url' => route('admin.templates.edit', $template)],
            ['label' => 'نسخه‌ها', 'url' => route('admin.templates.versions', $template)],
            ['label' => 'نسخه '.fa_num($version->version)],
        ],
    ])
@endsection

@section('content')
    @php $canManage = auth()->user()?->can('update', $template) ?? false; @endphp

    <div class="page-heading">
        <h1>نسخه {{ fa_num($version->version) }}</h1>
        @if ((int) $version->version === (int) $template->version)
            <span class="badge text-bg-primary">نسخه فعلی</span>
        @endif

        <div class="ms-auto d-flex gap-2">
            @if ($canManage && (int) $version->version !== (int) $template->version)
                <form method="POST" action="{{ route('admin.templates.versions.revert', [$template, $version]) }}"
                      data-confirm="آیا از بازگردانی قالب به این نسخه مطمئن هستید؟">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-arrow-counterclockwise"></i> بازگردانی به این نسخه
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.templates.versions', $template) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header card-title">اطلاعات نسخه</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>شماره نسخه</dt>
                        <dd>{{ fa_num($version->version) }}</dd>
                        <dt>توضیح</dt>
                        <dd>{{ $version->note ?: '—' }}</dd>
                        <dt>سازنده</dt>
                        <dd>{{ $version->creator?->name ?? '—' }}</dd>
                        <dt>تاریخ ثبت</dt>
                        <dd><x-jdate :date="$version->created_at" format="Y/m/d H:i" /></dd>
                        <dt>اندازه صفحه</dt>
                        <dd>{{ $version->page_size }}</dd>
                        <dt>جهت صفحه</dt>
                        <dd>{{ $version->orientation }}</dd>
                        <dt>حاشیه‌ها</dt>
                        <dd class="ltr">{{ json_encode($version->margins, JSON_UNESCAPED_UNICODE) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">متغیرهای این نسخه</div>
                <div class="table-responsive">
                    @if (empty($variables))
                        <x-empty icon="bi-braces" title="متغیری ثبت نشده است" />
                    @else
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>نام</th>
                                    <th>نوع</th>
                                    <th>الزامی</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($variables as $variable)
                                    <tr>
                                        <td><code>{{ $variable['name'] ?? '—' }}</code></td>
                                        <td class="small">{{ $variable['type'] ?? '—' }}</td>
                                        <td>
                                            @if (! empty($variable['required']))
                                                <span class="badge text-bg-warning">بله</span>
                                            @else
                                                <span class="badge text-bg-light border">خیر</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card mb-3">
                <div class="card-header card-title">HTML این نسخه</div>
                <div class="card-body">
                    <div class="log-viewer" style="max-height: 45vh">{{ $version->html }}</div>
                </div>
            </div>

            @if ($version->css)
                <div class="card mb-3">
                    <div class="card-header card-title">CSS این نسخه</div>
                    <div class="card-body">
                        <div class="log-viewer" style="max-height: 30vh">{{ $version->css }}</div>
                    </div>
                </div>
            @endif

            @if ($version->header_html || $version->footer_html)
                <div class="card">
                    <div class="card-header card-title">هدر / فوتر این نسخه</div>
                    <div class="card-body">
                        @if ($version->header_html)
                            <p class="form-label mb-1">هدر</p>
                            <div class="log-viewer mb-3" style="max-height: 20vh">{{ $version->header_html }}</div>
                        @endif
                        @if ($version->footer_html)
                            <p class="form-label mb-1">فوتر</p>
                            <div class="log-viewer" style="max-height: 20vh">{{ $version->footer_html }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
