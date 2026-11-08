@extends('layouts.app')

@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('admin.templates.update', $template) : route('admin.templates.store');
    $margins = old('margins', $template->marginsArray());
    $pdfOptions = old('pdf_options', $template->pdfOptionsArray());
    $pageSizes = \App\Enums\TemplatePageSize::options();
    $orientations = \App\Enums\TemplateOrientation::options();
    $variableTypes = \App\Enums\TemplateVariableType::options();

    $rawRows = old('variables');

    if (is_array($rawRows)) {
        $variableRows = array_values($rawRows);
    } else {
        $variableRows = $variables
            ->map(fn ($variable) => [
                'name' => $variable->name,
                'label' => $variable->label,
                'type' => $variable->type->value,
                'is_required' => $variable->is_required ? '1' : '0',
                'default_value' => $variable->default_value,
                'description' => $variable->description,
                'validation_rules' => $variable->validation_rules,
                'min_length' => $variable->min_length,
                'max_length' => $variable->max_length,
                'min_value' => $variable->min_value,
                'max_value' => $variable->max_value,
                'sort_order' => $variable->sort_order,
            ])
            ->values()
            ->all();
    }

    $canManage = auth()->user()?->can('update', $template) ?? false;
    $foreachSnippet = "@foreach(\$items as \$item)\n    {{ item.name }}\n@endforeach";
@endphp

@section('title', $isEdit ? 'ویرایش قالب' : 'قالب جدید')
@section('heading', $isEdit ? 'ویرایش قالب: '.$template->name : 'ایجاد قالب جدید')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'قالب‌ها', 'url' => route('admin.templates.index')],
            ['label' => $isEdit ? $template->name : 'قالب جدید'],
        ],
    ])
@endsection

@section('content')
    <form method="POST" action="{{ $action }}" id="templateForm" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-header card-title">اطلاعات پایه</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="name">نام قالب <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $template->name) }}"
                                       required maxlength="150">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="slug">اسلاگ (شناسه API) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control ltr @error('slug') is-invalid @enderror"
                                       id="slug" name="slug" value="{{ old('slug', $template->slug) }}"
                                       required maxlength="150" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                                <div class="form-text">حروف کوچک انگلیسی، اعداد و خط تیره؛ در API با همین مقدار ارجاع می‌شود.</div>
                                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="category">دسته‌بندی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control ltr @error('category') is-invalid @enderror"
                                       id="category" name="category" list="category-options"
                                       value="{{ old('category', $template->category ?? 'general') }}" required maxlength="60">
                                <datalist id="category-options">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}"></option>
                                    @endforeach
                                </datalist>
                                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="locale">زبان قالب</label>
                                <select class="form-select" id="locale" name="locale">
                                    @foreach (['fa' => 'فارسی (fa)', 'en' => 'English (en)', 'ar' => 'العربية (ar)'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('locale', $template->locale ?? 'fa') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label d-block">وضعیت</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           value="1" @checked((bool) old('is_active', $template->is_active ?? true))>
                                    <label class="form-check-label" for="is_active">قالب فعال باشد</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">توضیحات</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="2" maxlength="2000">{{ old('description', $template->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs card-header-tabs px-2 pt-2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-html"
                                        type="button" role="tab">
                                    <i class="bi bi-code-slash"></i> HTML اصلی
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-css"
                                        type="button" role="tab">
                                    <i class="bi bi-brush"></i> CSS
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-header"
                                        type="button" role="tab">
                                    <i class="bi bi-layout-text-window-reverse"></i> هدر
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-footer"
                                        type="button" role="tab">
                                    <i class="bi bi-layout-text-window"></i> فوتر
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="pane-html" role="tabpanel">
                                <label class="form-label" for="html">قالب HTML <span class="text-danger">*</span></label>
                                <textarea class="code-editor @error('html') is-invalid @enderror" id="html" name="html"
                                          rows="20" spellcheck="false" dir="ltr">{{ old('html', $template->html) }}</textarea>
                                @error('html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    سینتکس امن: <code>@{{ variable }}</code>،
                                    <code>@{{ value | number }}</code>،
                                    <code>@@if(...) … @@endif</code>،
                                    <code>@@foreach($items as $item) … @@endforeach</code>،
                                    <code>@@jdate($date)</code>.
                                    هرگز از کد PHP استفاده نکنید.
                                </div>
                            </div>
                            <div class="tab-pane fade" id="pane-css" role="tabpanel">
                                <label class="form-label" for="css">استایل CSS</label>
                                <textarea class="code-editor @error('css') is-invalid @enderror" id="css" name="css"
                                          rows="20" spellcheck="false" dir="ltr">{{ old('css', $template->css) }}</textarea>
                                @error('css')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    برای فونت: <code>@font-face { src: url('asset://vazirmatn-regular'); }</code>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="pane-header" role="tabpanel">
                                <label class="form-label" for="header_html">هدر صفحه (Header)</label>
                                <textarea class="code-editor code-editor--sm @error('header_html') is-invalid @enderror"
                                          id="header_html" name="header_html" rows="8"
                                          spellcheck="false" dir="ltr">{{ old('header_html', $template->header_html) }}</textarea>
                                @error('header_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    فقط با فعال‌بودن گزینه «نمایش هدر/فوتر» در تنظیمات PDF ارسال می‌شود.
                                </div>
                            </div>
                            <div class="tab-pane fade" id="pane-footer" role="tabpanel">
                                <label class="form-label" for="footer_html">فوتر صفحه (Footer)</label>
                                <textarea class="code-editor code-editor--sm @error('footer_html') is-invalid @enderror"
                                          id="footer_html" name="footer_html" rows="8"
                                          spellcheck="false" dir="ltr">{{ old('footer_html', $template->footer_html) }}</textarea>
                                @error('footer_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    شماره صفحه: <code>&lt;span class="pageNumber"&gt;&lt;/span&gt;</code> از
                                    <code>&lt;span class="totalPages"&gt;&lt;/span&gt;</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="hint-text"><i class="bi bi-braces"></i> درج سریع در ادیتور HTML:</span>
                            <div class="variable-chips">
                                @foreach ($variables as $variable)
                                    @php $insertSnippet = '{{ '.$variable->name.' }}'; @endphp
                                    <button type="button" class="variable-chip"
                                            data-target="#html" data-insert="{{ $insertSnippet }}"
                                            title="{{ $variable->label }} ({{ $variable->type->value }})">
                                        {{ $variable->name }}
                                    </button>
                                @endforeach
                                <button type="button" class="variable-chip" data-target="#html"
                                        data-insert="{{ $foreachSnippet }}" title="حلقه روی آرایه items">
                                    <i class="bi bi-arrow-repeat"></i> foreach
                                </button>
                                <button type="button" class="variable-chip" data-target="#html"
                                        data-insert="&#10;@jdate(now(), 'j F Y')" title="درج تاریخ شمسی">
                                    <i class="bi bi-calendar3"></i> jdate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card mb-3">
                    <div class="card-body d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            {{ $isEdit ? 'ذخیره تغییرات' : 'ایجاد قالب' }}
                        </button>
                        <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-right"></i>
                            بازگشت به لیست
                        </a>

                        @if ($isEdit)
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.templates.preview', $template) }}"
                                   class="btn btn-outline-primary flex-grow-1">
                                    <i class="bi bi-eye"></i> پیش‌نمایش
                                </a>
                                <a href="{{ route('admin.templates.versions', $template) }}"
                                   class="btn btn-outline-secondary flex-grow-1">
                                    <i class="bi bi-clock-history"></i> نسخه‌ها
                                </a>
                            </div>

                            <div class="mb-0">
                                <label class="form-label" for="version_note">توضیح نسخه (اختیاری)</label>
                                <input type="text" class="form-control" id="version_note" name="version_note"
                                       maxlength="255" placeholder="مثلاً: اصلاح حاشیه و فونت">
                                <div class="form-text">در صورت تغییر محتوا، نسخه جدید با این توضیح ثبت می‌شود.</div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($isEdit)
                    <div class="card mb-3">
                        <div class="card-header card-title">اطلاعات نسخه</div>
                        <div class="card-body">
                            <dl class="kv-list mb-0">
                                <dt>نسخه فعلی</dt>
                                <dd><span class="badge text-bg-primary">{{ fa_num($template->version) }}</span></dd>
                                <dt>سازنده</dt>
                                <dd>{{ $template->creator?->name ?? '—' }}</dd>
                                <dt>آخرین ویرایشگر</dt>
                                <dd>{{ $template->updater?->name ?? '—' }}</dd>
                                <dt>تاریخ ایجاد</dt>
                                <dd><x-jdate :date="$template->created_at" format="Y/m/d H:i" /></dd>
                                <dt>آخرین به‌روزرسانی</dt>
                                <dd><x-jdate :date="$template->updated_at" format="Y/m/d H:i" /></dd>
                                <dt>تعداد متغیرها</dt>
                                <dd>{{ fa_num($variables->count()) }}</dd>
                            </dl>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header card-title">تنظیمات صفحه و PDF</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="page_size">اندازه صفحه</label>
                                <select class="form-select" id="page_size" name="page_size">
                                    @foreach ($pageSizes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('page_size', $template->page_size?->value ?? 'A4') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="orientation">جهت صفحه</label>
                                <select class="form-select" id="orientation" name="orientation">
                                    @foreach ($orientations as $value => $label)
                                        <option value="{{ $value }}" @selected(old('orientation', $template->orientation?->value ?? 'portrait') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">حاشیه‌ها (میلی‌متر)</label>
                                <div class="row g-2">
                                    @foreach (['top' => 'بالا', 'right' => 'راست', 'bottom' => 'پایین', 'left' => 'چپ'] as $side => $label)
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">{{ $label }}</span>
                                                <input type="number" class="form-control ltr" min="0" max="100" step="0.1"
                                                       name="margins[{{ $side }}]" id="margin-{{ $side }}"
                                                       value="{{ $margins[$side] ?? 12 }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">گزینه‌های PDF</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="pdf_options[print_background]" value="0">
                                    <input class="form-check-input" type="checkbox" id="print_background"
                                           name="pdf_options[print_background]" value="1"
                                           @checked((bool) ($pdfOptions['print_background'] ?? true))>
                                    <label class="form-check-label" for="print_background">چاپ پس‌زمینه</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="pdf_options[prefer_css_page_size]" value="0">
                                    <input class="form-check-input" type="checkbox" id="prefer_css_page_size"
                                           name="pdf_options[prefer_css_page_size]" value="1"
                                           @checked((bool) ($pdfOptions['prefer_css_page_size'] ?? false))>
                                    <label class="form-check-label" for="prefer_css_page_size">اولویت اندازه صفحه CSS</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="pdf_options[display_header_footer]" value="0">
                                    <input class="form-check-input" type="checkbox" id="display_header_footer"
                                           name="pdf_options[display_header_footer]" value="1"
                                           @checked((bool) ($pdfOptions['display_header_footer'] ?? false))>
                                    <label class="form-check-label" for="display_header_footer">نمایش هدر/فوتر</label>
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label" for="scale">مقیاس</label>
                                <input type="number" class="form-control ltr" id="scale" name="pdf_options[scale]"
                                       min="0.1" max="2" step="0.05"
                                       value="{{ $pdfOptions['scale'] ?? 1 }}">
                            </div>

                            <div class="col-6">
                                <label class="form-label" for="custom_width">عرض سفارشی (mm)</label>
                                <input type="number" class="form-control ltr" id="custom_width"
                                       name="pdf_options[custom_width]" min="10" max="2000" step="0.1"
                                       value="{{ $pdfOptions['width'] ?? '' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="custom_height">ارتفاع سفارشی (mm)</label>
                                <input type="number" class="form-control ltr" id="custom_height"
                                       name="pdf_options[custom_height]" min="10" max="2000" step="0.1"
                                       value="{{ $pdfOptions['height'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="card-title">
                    <i class="bi bi-braces"></i>
                    متغیرهای قالب
                    <span class="badge text-bg-light border ms-1">{{ fa_num(count($variableRows)) }}</span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-row-add="#variablesBody" data-template="#variable-row-template">
                    <i class="bi bi-plus-lg"></i>
                    افزودن متغیر
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="min-width: 120px">نام (identifier)</th>
                            <th style="min-width: 130px">برچسب</th>
                            <th style="min-width: 130px">نوع</th>
                            <th style="min-width: 70px">الزامی</th>
                            <th style="min-width: 120px">پیش‌فرض</th>
                            <th style="min-width: 140px">توضیحات</th>
                            <th style="min-width: 140px">قواعد</th>
                            <th style="min-width: 90px">حداقل/حداکثر طول</th>
                            <th style="min-width: 90px">حداقل/حداکثر مقدار</th>
                            <th style="min-width: 70px">ترتیب</th>
                            <th style="width: 44px"></th>
                        </tr>
                    </thead>
                    <tbody id="variablesBody" data-next-index="{{ count($variableRows) }}">
                        @foreach ($variableRows as $index => $row)
                            @php
                                $rowName = (string) ($row['name'] ?? '');
                                $rowLabel = (string) ($row['label'] ?? '');
                                $rowType = (string) ($row['type'] ?? 'string');
                                $rowRequired = filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL);
                                $rowDefault = (string) ($row['default_value'] ?? '');
                                $rowDescription = (string) ($row['description'] ?? '');
                                $rowRules = (string) ($row['validation_rules'] ?? '');
                                $rowMinLength = (string) ($row['min_length'] ?? '');
                                $rowMaxLength = (string) ($row['max_length'] ?? '');
                                $rowMinValue = (string) ($row['min_value'] ?? '');
                                $rowMaxValue = (string) ($row['max_value'] ?? '');
                                $rowSort = (string) ($row['sort_order'] ?? ($index + 1));
                            @endphp
                            <tr data-row class="variable-row">
                                <td>
                                    <input type="text" class="form-control form-control-sm ltr" name="variables[{{ $index }}][name]"
                                           value="{{ $rowName }}" maxlength="60" pattern="[A-Za-z_][A-Za-z0-9_]*"
                                           title="فقط حروف انگلیسی، اعداد و زیرخط">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="variables[{{ $index }}][label]"
                                           value="{{ $rowLabel }}" maxlength="120">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="variables[{{ $index }}][type]">
                                        @foreach ($variableTypes as $value => $label)
                                            <option value="{{ $value }}" @selected($rowType === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="variables[{{ $index }}][is_required]" value="0">
                                    <input type="checkbox" class="form-check-input" name="variables[{{ $index }}][is_required]"
                                           value="1" @checked($rowRequired)>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="variables[{{ $index }}][default_value]"
                                           value="{{ $rowDefault }}" maxlength="5000">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="variables[{{ $index }}][description]"
                                           value="{{ $rowDescription }}" maxlength="1000">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm ltr" name="variables[{{ $index }}][validation_rules]"
                                           value="{{ $rowRules }}" maxlength="500" placeholder="max:120|email">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="number" class="form-control ltr" name="variables[{{ $index }}][min_length]"
                                               value="{{ $rowMinLength }}" min="0" max="65535" placeholder="min">
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control ltr" name="variables[{{ $index }}][max_length]"
                                               value="{{ $rowMaxLength }}" min="0" max="65535" placeholder="max">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="number" class="form-control ltr" name="variables[{{ $index }}][min_value]"
                                               value="{{ $rowMinValue }}" step="any" placeholder="min">
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control ltr" name="variables[{{ $index }}][max_value]"
                                               value="{{ $rowMaxValue }}" step="any" placeholder="max">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm ltr" name="variables[{{ $index }}][sort_order]"
                                           value="{{ $rowSort }}" min="0" max="65535">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-row-remove
                                            title="حذف متغیر">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white">
                <p class="hint-text mb-0">
                    نام متغیر در قالب با <code>@{{ variable_name }}</code> استفاده می‌شود.
                    متغیرهای آرایه‌ای را با <code>@@foreach($name as $item) … @@endforeach</code> پیمایش کنید.
                    تغییر در متغیرها همراه با محتوا باعث ثبت نسخه جدید می‌شود.
                </p>
            </div>
        </div>

        @if ($isEdit)
            <div class="card mt-3 border-danger-subtle">
                <div class="card-header card-title text-danger">ناحیه خطر</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.templates.toggle', $template) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi {{ $template->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            {{ $template->is_active ? 'غیرفعال‌سازی قالب' : 'فعال‌سازی قالب' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.templates.destroy', $template) }}"
                          data-confirm="آیا از حذف قالب «{{ $template->name }}» مطمئن هستید؟">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i>
                            حذف قالب
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </form>

    <template id="variable-row-template">
        <tr data-row class="variable-row">
            <td>
                <input type="text" class="form-control form-control-sm ltr" name="variables[__INDEX__][name]"
                       value="" maxlength="60" pattern="[A-Za-z_][A-Za-z0-9_]*">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="variables[__INDEX__][label]"
                       value="" maxlength="120">
            </td>
            <td>
                <select class="form-select form-select-sm" name="variables[__INDEX__][type]">
                    @foreach ($variableTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td class="text-center">
                <input type="hidden" name="variables[__INDEX__][is_required]" value="0">
                <input type="checkbox" class="form-check-input" name="variables[__INDEX__][is_required]" value="1">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="variables[__INDEX__][default_value]"
                       value="" maxlength="5000">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="variables[__INDEX__][description]"
                       value="" maxlength="1000">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm ltr" name="variables[__INDEX__][validation_rules]"
                       value="" maxlength="500" placeholder="max:120|email">
            </td>
            <td>
                <div class="input-group input-group-sm mb-1">
                    <input type="number" class="form-control ltr" name="variables[__INDEX__][min_length]" value=""
                           min="0" max="65535" placeholder="min">
                </div>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control ltr" name="variables[__INDEX__][max_length]" value=""
                           min="0" max="65535" placeholder="max">
                </div>
            </td>
            <td>
                <div class="input-group input-group-sm mb-1">
                    <input type="number" class="form-control ltr" name="variables[__INDEX__][min_value]" value=""
                           step="any" placeholder="min">
                </div>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control ltr" name="variables[__INDEX__][max_value]" value=""
                           step="any" placeholder="max">
                </div>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm ltr" name="variables[__INDEX__][sort_order]"
                       value="__INDEX__" min="0" max="65535">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" data-row-remove title="حذف متغیر">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>
    </template>
@endsection
