@extends('layouts.app')

@section('title', 'پیش‌نمایش قالب')
@section('heading', 'پیش‌نمایش قالب: '.$template->name)

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'قالب‌ها', 'url' => route('admin.templates.index')],
            ['label' => $template->name, 'url' => route('admin.templates.edit', $template)],
            ['label' => 'پیش‌نمایش'],
        ],
    ])
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">داده نمونه (JSON)</span>
                    <span class="badge text-bg-light border">{{ fa_num($variables->count()) }} متغیر</span>
                </div>

                <form method="POST" action="{{ route('admin.templates.preview.html', $template) }}"
                      target="_blank" data-validate-json novalidate>
                    @csrf

                    <div class="card-body">
                        <textarea class="code-editor code-editor--sm" id="previewData" name="data"
                                  rows="16" spellcheck="false" data-json dir="ltr">{{ old('data', $sampleJson) }}</textarea>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-json-format="#previewData" data-json-feedback="#preview-json-feedback">
                                <i class="bi bi-braces"></i> مرتب‌سازی JSON
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-filetype-html"></i> پیش‌نمایش HTML
                            </button>
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    formaction="{{ route('admin.templates.preview.pdf', $template) }}">
                                <i class="bi bi-file-earmark-pdf"></i> پیش‌نمایش PDF
                            </button>
                        </div>
                        <div id="preview-json-feedback" class="form-text mt-2">
                            داده‌ها فقط برای این پیش‌نمایش استفاده می‌شوند و ذخیره نمی‌شوند.
                        </div>
                    </div>
                </form>

                <div class="card-footer bg-white">
                    <p class="hint-text mb-0">
                        <i class="bi bi-info-circle"></i>
                        پیش‌نمایش PDF از موتور Gotenberg استفاده می‌کند؛ اگر سرویس در دسترس نباشد پیام خطای کنترل‌شده
                        نمایش داده می‌شود و پنل دچار خطا نمی‌شود.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-3">
                <div class="card-header card-title">اطلاعات قالب</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>نام</dt>
                        <dd>{{ $template->name }}</dd>
                        <dt>اسلاگ</dt>
                        <dd><code>{{ $template->slug }}</code></dd>
                        <dt>نسخه</dt>
                        <dd><span class="badge text-bg-primary">{{ fa_num($template->version) }}</span></dd>
                        <dt>اندازه / جهت صفحه</dt>
                        <dd>{{ $template->page_size?->label() }} — {{ $template->orientation?->label() }}</dd>
                        <dt>وضعیت</dt>
                        <dd>
                            <span class="badge {{ $template->statusBadgeClass() }}">{{ $template->statusLabel() }}</span>
                        </dd>
                    </dl>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i> ویرایش
                    </a>
                    <a href="{{ route('admin.templates.versions', $template) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-clock-history"></i> نسخه‌ها
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">متغیرهای قالب</div>
                <div class="table-responsive">
                    @if ($variables->isEmpty())
                        <x-empty icon="bi-braces" title="متغیری تعریف نشده است" />
                    @else
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>نام</th>
                                    <th>برچسب</th>
                                    <th>نوع</th>
                                    <th>الزامی</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($variables as $variable)
                                    <tr>
                                        <td><code>{{ $variable->name }}</code></td>
                                        <td>{{ $variable->label }}</td>
                                        <td class="small">{{ $variable->type->label() }}</td>
                                        <td>
                                            @if ($variable->is_required)
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
    </div>
@endsection
