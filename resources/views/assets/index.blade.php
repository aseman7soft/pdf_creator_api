@extends('layouts.app')

@section('title', 'فایل‌ها و فونت‌ها')
@section('heading', 'فایل‌ها و فونت‌ها')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'فایل‌ها و فونت‌ها']]])
@endsection

@push('styles')
    @foreach ($assets as $asset)
        @if ($asset->type === \App\Enums\AssetType::Font)
            <style>
                @font-face {
                    font-family: 'preview-font-{{ $asset->id }}';
                    src: url('{{ route('admin.assets.file', $asset) }}');
                    font-display: swap;
                }
            </style>
        @endif
    @endforeach
@endpush

@section('content')
    @php $canManage = auth()->user()?->canManage() ?? false; @endphp

    <div class="page-heading">
        <h1>فایل‌ها و فونت‌ها</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($assets->total())) }} فایل</span>
        @foreach ($types as $value => $label)
            <a class="badge {{ ($filters['type'] ?? '') === $value ? 'text-bg-primary' : 'text-bg-light border' }}"
               href="{{ route('admin.assets.index', ['type' => $value]) }}">
                {{ $label }} ({{ fa_num(number_format($counts[$value] ?? 0)) }})
            </a>
        @endforeach
    </div>

    @if ($canManage)
        <div class="card mb-3">
            <div class="card-header card-title"><i class="bi bi-cloud-arrow-up"></i> بارگذاری فایل جدید</div>
            <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="file">فایل <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                حداکثر حجم: {{ fa_num(number_format($maxUploadKb)) }} کیلوبایت.
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="type">نوع</label>
                            <select class="form-select" id="type" name="type" required>
                                @foreach ($types as $value => $label)
                                    @continue($value === 'other')
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="asset_name">نام نمایشی</label>
                            <input type="text" class="form-control" id="asset_name" name="name"
                                   value="{{ old('name') }}" maxlength="150" placeholder="اختیاری">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="asset_description">توضیح</label>
                            <input type="text" class="form-control" id="asset_description" name="description"
                                   value="{{ old('description') }}" maxlength="500" placeholder="اختیاری">
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="bi bi-shield-lock"></i>
                        فقط فرمت‌های مجاز پذیرفته می‌شوند:
                        <strong>فونت</strong>: {{ implode('، ', $allowedExtensions['font']) }} —
                        <strong>تصویر</strong>: {{ implode('، ', $allowedExtensions['image']) }} —
                        <strong>CSS</strong>: {{ implode('، ', $allowedExtensions['css']) }}.
                        فایل‌های PHP، SVG، HTML و JS هرگز پذیرفته نمی‌شوند (اعتبارسنجی پسوند و MIME).
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> بارگذاری
                    </button>
                </div>
            </form>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.assets.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="نام، handle یا توضیحات">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="filter_type">نوع</label>
                <select class="form-select" id="filter_type" name="type">
                    <option value="">همه</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('admin.assets.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($assets->isEmpty())
                <x-empty icon="bi-images" title="فایلی یافت نشد"
                         text="فونت وزیرمتن به صورت خودکار seed شده است؛ می‌توانید فونت یا تصویر جدید بارگذاری کنید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>پیش‌نمایش</th>
                            <th>نام / handle</th>
                            <th>نوع</th>
                            <th>MIME</th>
                            <th>حجم</th>
                            <th>بارگذار</th>
                            <th>تاریخ</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            <tr>
                                <td style="width: 110px;">
                                    @if ($asset->type === \App\Enums\AssetType::Image)
                                        <img src="{{ route('admin.assets.file', $asset) }}" alt="{{ $asset->name }}"
                                             class="image-thumb" loading="lazy">
                                    @elseif ($asset->type === \App\Enums\AssetType::Font)
                                        <span class="font-preview d-block" style="font-family: 'preview-font-{{ $asset->id }}', Vazirmatn, Tahoma;">
                                            آب پ الف ۱۲۳
                                        </span>
                                    @elseif ($asset->type === \App\Enums\AssetType::Css)
                                        <i class="bi bi-filetype-css fs-3 text-info"></i>
                                    @else
                                        <i class="bi bi-file-earmark fs-3 text-secondary"></i>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $asset->name }}</div>
                                    <code>{{ $asset->handle }}</code>
                                    @if (! $asset->exists())
                                        <span class="badge text-bg-danger">فایل روی دیسک نیست</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $asset->type->badgeClass() }}">{{ $asset->type->label() }}</span>
                                </td>
                                <td class="small ltr">{{ $asset->mime_type }}</td>
                                <td class="small">{{ $asset->humanFileSize() }}</td>
                                <td class="small">{{ $asset->uploader?->name ?? '—' }}</td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$asset->created_at" format="Y/m/d" />
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-copy="{{ $asset->referenceToken() }}"
                                                title="کپی ارجاع asset://">
                                            <i class="bi bi-clipboard"></i>
                                            <span class="d-none d-xl-inline">asset://</span>
                                        </button>
                                        <a href="{{ route('admin.assets.file', $asset) }}" target="_blank"
                                           class="btn btn-sm btn-outline-primary" title="مشاهده فایل">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.assets.download', $asset) }}"
                                           class="btn btn-sm btn-outline-primary" title="دانلود">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @can('delete', $asset)
                                            <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}"
                                                  data-confirm="فایل «{{ $asset->name }}» و رکورد آن حذف شود؟ قالب‌هایی که به آن ارجاع دارند دچار خطا می‌شوند.">
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

        @if ($assets->hasPages())
            <div class="card-body border-top">{{ $assets->links() }}</div>
        @endif

        <div class="card-footer bg-white">
            <p class="hint-text mb-0">
                <i class="bi bi-info-circle"></i>
                برای استفاده در قالب از ارجاع <code>asset://handle</code> استفاده کنید؛
                مثال: <code>@font-face { src: url('asset://handle'); }</code> یا
                <code>&lt;img src="asset://handle"&gt;</code>.
            </p>
        </div>
    </div>
@endsection
