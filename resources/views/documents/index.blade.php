@extends('layouts.app')

@section('title', 'اسناد تولیدشده')
@section('heading', 'اسناد تولیدشده')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'اسناد']]])
@endsection

@section('content')
    @php $canManage = auth()->user()?->canManage() ?? false; @endphp

    <div class="page-heading">
        <h1>اسناد</h1>
        <span class="badge text-bg-light border">{{ fa_num(number_format($documents->total())) }} سند</span>
    </div>

    <form method="GET" action="{{ route('admin.documents.index') }}" class="filters-card">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="search">جستجو</label>
                <input type="search" class="form-control" id="search" name="search"
                       value="{{ $filters['search'] ?? '' }}" placeholder="reference، ulid یا نام قالب">
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
                <label class="form-label" for="template_id">قالب</label>
                <select class="form-select" id="template_id" name="template_id">
                    <option value="">همه</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected((int) ($filters['template_id'] ?? 0) === $template->id)>
                            {{ $template->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_from">از تاریخ (میلادی)</label>
                <input type="date" class="form-control ltr" id="date_from" name="date_from" dir="ltr"
                       value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_to">تا تاریخ (میلادی)</label>
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
                <a href="{{ route('admin.documents.index') }}" class="btn btn-outline-secondary" title="حذف فیلترها">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
        <p class="hint-text mb-0 mt-2">
            <i class="bi bi-info-circle"></i>
            تاریخ‌های وارد‌شده میلادی هستند و در منطقه زمانی تهران تفسیر می‌شوند؛ تاریخ‌های نمایش‌داده‌شده شمسی‌اند.
        </p>
    </form>

    <div class="card">
        <div class="table-responsive">
            @if ($documents->isEmpty())
                <x-empty icon="bi-file-earmark-x" title="سندی یافت نشد"
                         text="با تغییر فیلترها دوباره تلاش کنید یا از طریق API سند تولید کنید." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>شناسه / مرجع</th>
                            <th>قالب</th>
                            <th>پروژه</th>
                            <th>حالت</th>
                            <th>وضعیت</th>
                            <th>حجم</th>
                            <th>تاریخ</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td>
                                    <a class="ltr d-block" href="{{ route('admin.documents.show', $document) }}">
                                        {{ \Illuminate\Support\Str::limit($document->ulid, 14, '…') }}
                                    </a>
                                    <span class="small text-secondary ltr">{{ $document->reference ?: '—' }}</span>
                                </td>
                                <td>
                                    {{ $document->template?->name ?? $document->template_name ?? '—' }}
                                    <span class="badge text-bg-light border">v{{ fa_num($document->template_version ?? '—') }}</span>
                                </td>
                                <td class="small">{{ $document->project?->name ?? '—' }}</td>
                                <td class="small">{{ $document->generation_mode?->label() ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $document->status->badgeClass() }}">
                                        {{ $document->status->label() }}
                                    </span>
                                </td>
                                <td class="small">{{ $document->humanFileSize() }}</td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$document->created_at" format="Y/m/d H:i" />
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.documents.show', $document) }}"
                                           class="btn btn-sm btn-outline-secondary" title="جزئیات">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($document->canDownload())
                                            <a href="{{ route('admin.documents.download', $document) }}"
                                               class="btn btn-sm btn-outline-primary" title="دانلود">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        @endif
                                        @if ($canManage && $document->status === \App\Enums\DocumentStatus::Failed)
                                            <form method="POST" action="{{ route('admin.documents.retry', $document) }}"
                                                  data-confirm="تولید مجدد این سند در صف قرار می‌گیرد. ادامه می‌دهید؟">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="تلاش مجدد">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @can('delete', $document)
                                            <form method="POST" action="{{ route('admin.documents.destroy', $document) }}"
                                                  data-confirm="آیا از حذف این سند و فایل PDF آن مطمئن هستید؟">
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

        @if ($documents->hasPages())
            <div class="card-body border-top">{{ $documents->links() }}</div>
        @endif
    </div>
@endsection
