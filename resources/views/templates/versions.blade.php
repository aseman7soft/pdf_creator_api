@extends('layouts.app')

@section('title', 'نسخه‌های قالب')
@section('heading', 'نسخه‌های قالب: '.$template->name)

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'قالب‌ها', 'url' => route('admin.templates.index')],
            ['label' => $template->name, 'url' => route('admin.templates.edit', $template)],
            ['label' => 'نسخه‌ها'],
        ],
    ])
@endsection

@section('content')
    @php $canManage = auth()->user()?->can('update', $template) ?? false; @endphp

    <div class="page-heading">
        <h1>نسخه‌ها</h1>
        <span class="badge text-bg-primary">نسخه فعلی: {{ fa_num($template->version) }}</span>
        <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-outline-secondary ms-auto">
            <i class="bi bi-pencil"></i> ویرایش قالب
        </a>
        <a href="{{ route('admin.templates.preview', $template) }}" class="btn btn-outline-primary">
            <i class="bi bi-eye"></i> پیش‌نمایش
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            @if ($versions->isEmpty())
                <x-empty icon="bi-clock-history" title="نسخه‌ای ثبت نشده است"
                         text="با اولین ویرایش و ذخیره قالب، نسخه اولیه ثبت می‌شود." />
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>نسخه</th>
                            <th>توضیح</th>
                            <th>سازنده</th>
                            <th>تاریخ ثبت</th>
                            <th>تعداد متغیر</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $version)
                            <tr>
                                <td>
                                    <span class="badge {{ (int) $version->version === (int) $template->version ? 'text-bg-primary' : 'text-bg-light border' }}">
                                        {{ fa_num($version->version) }}
                                    </span>
                                </td>
                                <td class="small">{{ $version->note ?: '—' }}</td>
                                <td class="small">{{ $version->creator?->name ?? '—' }}</td>
                                <td class="small text-secondary">
                                    <x-jdate :date="$version->created_at" format="Y/m/d H:i" />
                                </td>
                                <td><x-fa :value="count($version->variablesSnapshot())" /></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.templates.versions.show', [$template, $version]) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> مشاهده
                                        </a>
                                        @if ($canManage && (int) $version->version !== (int) $template->version)
                                            <form method="POST"
                                                  action="{{ route('admin.templates.versions.revert', [$template, $version]) }}"
                                                  data-confirm="آیا از بازگردانی قالب به نسخه {{ $version->version }} مطمئن هستید؟ یک نسخه جدید از وضعیت فعلی ثبت می‌شود.">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-arrow-counterclockwise"></i> بازگردانی
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($versions->hasPages())
            <div class="card-body border-top">{{ $versions->links() }}</div>
        @endif
    </div>
@endsection
