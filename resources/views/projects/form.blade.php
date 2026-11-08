@extends('layouts.app')

@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('admin.projects.update', $project) : route('admin.projects.store');
@endphp

@section('title', $isEdit ? 'ویرایش پروژه API' : 'پروژه API جدید')
@section('heading', $isEdit ? 'ویرایش پروژه: '.$project->name : 'پروژه API جدید')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'پروژه‌های API', 'url' => route('admin.projects.index')],
            ['label' => $isEdit ? $project->name : 'پروژه جدید'],
        ],
    ])
@endsection

@section('content')
    <form method="POST" action="{{ $action }}" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card">
                    <div class="card-header card-title">اطلاعات پروژه</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="name">نام پروژه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $project->name) }}"
                                       required maxlength="120">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="code">کد پروژه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control ltr @error('code') is-invalid @enderror"
                                       id="code" name="code" value="{{ old('code', $project->code) }}"
                                       required maxlength="60" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                                <div class="form-text">حروف کوچک انگلیسی، اعداد و خط تیره؛ یکتا است.</div>
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">توضیحات</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="3"
                                          maxlength="2000">{{ old('description', $project->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="rate_limit_per_minute">سقف نرخ (در دقیقه)</label>
                                <input type="number" class="form-control ltr @error('rate_limit_per_minute') is-invalid @enderror"
                                       id="rate_limit_per_minute" name="rate_limit_per_minute" min="1" max="65535"
                                       value="{{ old('rate_limit_per_minute', $project->rate_limit_per_minute) }}"
                                       placeholder="{{ config('api.rate_limit_per_minute', 60) }}">
                                <div class="form-text">خالی = مقدار پیش‌فرض سیستم.</div>
                                @error('rate_limit_per_minute')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="monthly_quota">سقف ماهانه</label>
                                <input type="number" class="form-control ltr @error('monthly_quota') is-invalid @enderror"
                                       id="monthly_quota" name="monthly_quota" min="1"
                                       value="{{ old('monthly_quota', $project->monthly_quota) }}"
                                       placeholder="نامحدود">
                                <div class="form-text">خالی = بدون محدودیت ماهانه.</div>
                                @error('monthly_quota')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label d-block">وضعیت</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           value="1" @checked((bool) old('is_active', $project->is_active ?? true))>
                                    <label class="form-check-label" for="is_active">پروژه فعال باشد</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card mb-3">
                    <div class="card-header card-title">قالب‌های مجاز</div>
                    <div class="card-body">
                        <p class="hint-text">
                            اگر هیچ قالبی انتخاب نشود، پروژه به همه قالب‌های فعال دسترسی خواهد داشت.
                        </p>
                        @error('templates')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        <div class="border rounded p-2" style="max-height: 320px; overflow-y: auto;">
                            @forelse ($templates as $template)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="template-{{ $template->id }}" name="templates[]"
                                           value="{{ $template->id }}"
                                           @checked(in_array($template->id, old('templates', $selectedTemplates), true))>
                                    <label class="form-check-label" for="template-{{ $template->id }}">
                                        {{ $template->name }}
                                        <code class="ms-1">{{ $template->slug }}</code>
                                        @unless ($template->is_active)
                                            <span class="badge text-bg-secondary">غیرفعال</span>
                                        @endunless
                                    </label>
                                </div>
                            @empty
                                <p class="hint-text mb-0">قالبی برای انتخاب وجود ندارد.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            {{ $isEdit ? 'ذخیره تغییرات' : 'ایجاد پروژه' }}
                        </button>
                        <a href="{{ $isEdit ? route('admin.projects.show', $project) : route('admin.projects.index') }}"
                           class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-right"></i> بازگشت
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
