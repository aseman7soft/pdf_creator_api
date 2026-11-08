@extends('layouts.app')

@section('title', 'پروژه: '.$project->name)
@section('heading', 'پروژه API: '.$project->name)

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'پروژه‌های API', 'url' => route('admin.projects.index')],
            ['label' => $project->name],
        ],
    ])
@endsection

@section('content')
    @php
        $canManage = auth()->user()?->can('manageKeys', $project) ?? false;
        $canEdit = auth()->user()?->can('update', $project) ?? false;
    @endphp

    <div class="page-heading">
        <h1>{{ $project->name }}</h1>
        <code class="fs-6">{{ $project->code }}</code>
        <span class="badge {{ $project->statusBadgeClass() }}">{{ $project->statusLabel() }}</span>

        <div class="ms-auto d-flex gap-2">
            @if ($canEdit)
                <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil"></i> ویرایش
                </a>
                <form method="POST" action="{{ route('admin.projects.toggle', $project) }}">
                    @csrf
                    <button type="submit" class="btn {{ $project->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                        <i class="bi {{ $project->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                        {{ $project->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                      data-confirm="آیا از حذف پروژه «{{ $project->name }}» و کلیدهای آن مطمئن هستید؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($plainKey)
        <div class="alert alert-success border-2 border-success shadow-sm">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-key-fill fs-4"></i>
                <div class="flex-grow-1">
                    <strong class="d-block mb-1">
                        کلید API جدید{{ $plainKeyName ? ' — '.$plainKeyName : '' }}
                    </strong>
                    <p class="small mb-2">
                        این کلید کامل <strong>فقط همین یک بار</strong> نمایش داده می‌شود. آن را در جای امن ذخیره کنید؛
                        پس از بازآوری صفحه قابل بازیابی نیست.
                    </p>
                    <div class="secret-key mb-2" id="plain-key-value">{{ $plainKey }}</div>
                    <button type="button" class="btn btn-sm btn-success" data-copy="{{ $plainKey }}">
                        <i class="bi bi-clipboard"></i> کپی کلید
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__icon text-bg-primary bg-opacity-10"><i class="bi bi-file-earmark-pdf"></i></div>
                <div>
                    <p class="stat-card__label">کل اسناد پروژه</p>
                    <p class="stat-card__value">{{ fa_num(number_format($stats['documents'])) }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__icon text-bg-success bg-opacity-10"><i class="bi bi-check-circle"></i></div>
                <div>
                    <p class="stat-card__label">تکمیل‌شده</p>
                    <p class="stat-card__value">{{ fa_num(number_format($stats['documents_completed'])) }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__icon text-bg-danger bg-opacity-10"><i class="bi bi-x-octagon"></i></div>
                <div>
                    <p class="stat-card__label">ناموفق</p>
                    <p class="stat-card__value">{{ fa_num(number_format($stats['documents_failed'])) }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__icon text-bg-info bg-opacity-10"><i class="bi bi-activity"></i></div>
                <div>
                    <p class="stat-card__label">مصرف این ماه</p>
                    <p class="stat-card__value">
                        {{ fa_num(number_format($stats['requests_month'])) }}
                        @if ($project->monthly_quota)
                            <span class="fs-6 text-secondary">/ {{ fa_num(number_format($project->monthly_quota)) }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header card-title">مشخصات پروژه</div>
                <div class="card-body">
                    <dl class="kv-list mb-0">
                        <dt>کد</dt>
                        <dd><code>{{ $project->code }}</code></dd>
                        <dt>توضیحات</dt>
                        <dd>{{ $project->description ?: '—' }}</dd>
                        <dt>سقف نرخ</dt>
                        <dd>
                            <x-fa :value="$project->rate_limit_per_minute" fallback="پیش‌فرض" />
                            درخواست در دقیقه
                        </dd>
                        <dt>سقف ماهانه</dt>
                        <dd><x-fa :value="$project->monthly_quota" fallback="نامحدود" /></dd>
                        <dt>تعداد کلیدهای فعال</dt>
                        <dd>{{ fa_num($stats['active_keys']) }} از {{ fa_num($stats['api_keys']) }}</dd>
                        <dt>آخرین فعالیت</dt>
                        <dd><x-jdate :date="$stats['last_activity']" format="Y/m/d H:i" fallback="—" /></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">قالب‌های مجاز</div>
                <div class="card-body">
                    @if ($project->templates->isEmpty())
                        <p class="mb-0">
                            <span class="badge text-bg-light border">محدودیتی تعریف نشده</span>
                            این پروژه به همه قالب‌های فعال دسترسی دارد.
                        </p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach ($project->templates as $template)
                                <li class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                    <span>
                                        {{ $template->name }}
                                        <code class="ms-1">{{ $template->slug }}</code>
                                    </span>
                                    @unless ($template->is_active)
                                        <span class="badge text-bg-secondary">غیرفعال</span>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            @if ($canManage)
                <div class="card mb-3">
                    <div class="card-header card-title">
                        <i class="bi bi-plus-circle"></i> صدور کلید جدید
                    </div>
                    <form method="POST" action="{{ route('admin.projects.keys.store', $project) }}">
                        @csrf
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="key_name">نام کلید <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="key_name" name="name"
                                           value="{{ old('name') }}" required maxlength="120"
                                           placeholder="مثلاً: سرور اصلی">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="key_expires">تاریخ انقضا</label>
                                    <input type="date" class="form-control ltr" id="key_expires" name="expires_at"
                                           value="{{ old('expires_at') }}" dir="ltr">
                                    <div class="form-text">خالی = بدون انقضا</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="key_rate">سقف نرخ اختصاصی</label>
                                    <input type="number" class="form-control ltr" id="key_rate"
                                           name="rate_limit_per_minute" min="1" max="65535"
                                           value="{{ old('rate_limit_per_minute') }}" placeholder="پیش‌فرض پروژه">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-key"></i> صدور
                                    </button>
                                </div>
                            </div>
                            <p class="hint-text mb-0 mt-2">
                                کلید کامل فقط یک بار پس از صدور نمایش داده می‌شود.
                            </p>
                        </div>
                    </form>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title"><i class="bi bi-key"></i> کلیدهای API</span>
                    <span class="badge text-bg-light border">{{ fa_num($keys->whereNull('deleted_at')->count()) }} کلید</span>
                </div>
                <div class="table-responsive">
                    @if ($keys->isEmpty())
                        <x-empty icon="bi-key" title="کلیدی صادر نشده است"
                                 text="برای استفاده از API حداقل یک کلید بسازید." />
                    @else
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>نام</th>
                                    <th>پیشوند</th>
                                    <th>وضعیت</th>
                                    <th>انقضا</th>
                                    <th>آخرین استفاده</th>
                                    <th>سقف نرخ</th>
                                    <th class="text-end">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($keys as $key)
                                    <tr class="{{ $key->trashed() ? 'opacity-50' : '' }}">
                                        <td>
                                            {{ $key->name }}
                                            @if ($key->trashed())
                                                <span class="badge text-bg-secondary">حذف‌شده</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $key->maskedKey() }}</code></td>
                                        <td>
                                            <span class="badge {{ $key->statusBadgeClass() }}">{{ $key->statusLabel() }}</span>
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$key->expires_at" format="Y/m/d" fallback="بدون انقضا" />
                                        </td>
                                        <td class="small text-secondary">
                                            <x-jdate :date="$key->last_used_at" format="Y/m/d H:i" fallback="هرگز" />
                                        </td>
                                        <td>
                                            <x-fa :value="$key->rate_limit_per_minute" fallback="پیش‌فرض" />
                                        </td>
                                        <td class="text-end">
                                            @if (! $key->trashed())
                                                <div class="d-inline-flex gap-1">
                                                    @can('rotate', $key)
                                                        <form method="POST"
                                                              action="{{ route('admin.projects.keys.rotate', [$project, $key]) }}"
                                                              data-confirm="کلید فعلی لغو و کلید جدید صادر می‌شود. ادامه می‌دهید؟">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                    title="چرخش کلید">
                                                                <i class="bi bi-arrow-repeat"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                    @can('update', $key)
                                                        @if ($key->isUsable())
                                                            <form method="POST"
                                                                  action="{{ route('admin.projects.keys.revoke', [$project, $key]) }}"
                                                                  data-confirm="آیا از لغو این کلید مطمئن هستید؟">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                                        title="لغو کلید">
                                                                    <i class="bi bi-slash-circle"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('delete', $key)
                                                        <form method="POST"
                                                              action="{{ route('admin.projects.keys.destroy', [$project, $key]) }}"
                                                              data-confirm="آیا از حذف این کلید مطمئن هستید؟">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    title="حذف">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
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
