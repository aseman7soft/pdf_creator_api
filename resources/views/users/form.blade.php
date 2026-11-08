@extends('layouts.app')

@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('admin.users.update', $user) : route('admin.users.store');
@endphp

@section('title', $isEdit ? 'ویرایش کاربر' : 'کاربر جدید')
@section('heading', $isEdit ? 'ویرایش کاربر: '.$user->name : 'ایجاد کاربر جدید')

@section('breadcrumbs')
    @include('partials.breadcrumbs', [
        'items' => [
            ['label' => 'کاربران', 'url' => route('admin.users.index')],
            ['label' => $isEdit ? $user->name : 'کاربر جدید'],
        ],
    ])
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-header card-title">اطلاعات کاربر</div>
                <form method="POST" action="{{ $action }}" novalidate>
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="name">نام و نام خانوادگی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $user->name) }}"
                                       required maxlength="120">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="email">ایمیل <span class="text-danger">*</span></label>
                                <input type="email" class="form-control ltr @error('email') is-invalid @enderror"
                                       id="email" name="email" value="{{ old('email', $user->email) }}"
                                       required maxlength="190" dir="ltr">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role">نقش <span class="text-danger">*</span></label>
                                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role"
                                        @disabled($isSelf)>
                                    @foreach ($roles as $value => $label)
                                        <option value="{{ $value }}"
                                                @selected(old('role', $user->role?->value ?? 'viewer') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($isSelf)
                                    <div class="form-text text-warning">
                                        نمی‌توانید نقش خود را تغییر دهید. برای تغییر، از حساب مدیر دیگری استفاده کنید.
                                    </div>
                                @endif
                                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label d-block">وضعیت حساب</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           value="1" @checked((bool) old('is_active', $user->is_active ?? true))
                                           @disabled($isSelf)>
                                    <label class="form-check-label" for="is_active">
                                        حساب فعال باشد
                                        @if ($isSelf)
                                            <span class="text-warning">(حساب خودتان همیشه فعال است)</span>
                                        @endif
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="password">
                                    رمز عبور
                                    @unless ($isEdit)<span class="text-danger">*</span>@endunless
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control ltr @error('password') is-invalid @enderror"
                                           id="password" name="password" dir="ltr"
                                           @required(! $isEdit) autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button"
                                            data-password-toggle="#password" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                @if ($isEdit)
                                    <div class="form-text">خالی بگذارید تا رمز عبور تغییر نکند.</div>
                                @endif
                                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="password_confirmation">تکرار رمز عبور</label>
                                <input type="password" class="form-control ltr" id="password_confirmation"
                                       name="password_confirmation" dir="ltr" autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            {{ $isEdit ? 'ذخیره تغییرات' : 'ایجاد کاربر' }}
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-right"></i> بازگشت
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            @if ($isEdit)
                <div class="card mb-3">
                    <div class="card-header card-title">اطلاعات حساب</div>
                    <div class="card-body">
                        <dl class="kv-list mb-0">
                            <dt>شناسه</dt>
                            <dd><x-fa :value="$user->id" /></dd>
                            <dt>آخرین ورود</dt>
                            <dd><x-jdate :date="$user->last_login_at" format="Y/m/d H:i" fallback="هرگز" /></dd>
                            <dt>تاریخ عضویت</dt>
                            <dd><x-jdate :date="$user->created_at" format="Y/m/d H:i" /></dd>
                            <dt>آخرین به‌روزرسانی</dt>
                            <dd><x-jdate :date="$user->updated_at" format="Y/m/d H:i" /></dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header card-title">بازنشانی رمز عبور</div>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}">
                        @csrf
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label" for="new_password">رمز عبور جدید <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control ltr" id="new_password"
                                           name="password" dir="ltr" required autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button"
                                            data-password-toggle="#new_password" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="new_password_confirmation">تکرار رمز عبور جدید</label>
                                <input type="password" class="form-control ltr" id="new_password_confirmation"
                                       name="password_confirmation" dir="ltr" required autocomplete="new-password">
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-key"></i> بازنشانی رمز عبور
                            </button>
                        </div>
                    </form>
                </div>

                @unless ($isSelf)
                    <div class="card border-danger-subtle">
                        <div class="card-header card-title text-danger">ناحیه خطر</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                  data-confirm="آیا از حذف کاربر «{{ $user->name }}» مطمئن هستید؟ این عملیات قابل بازگشت نیست.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-trash"></i> حذف کاربر
                                </button>
                            </form>
                        </div>
                    </div>
                @endunless
            @else
                <div class="card">
                    <div class="card-header card-title">راهنمای نقش‌ها</div>
                    <div class="card-body">
                        <dl class="kv-list mb-0">
                            @foreach (\App\Enums\UserRole::cases() as $role)
                                <dt>{{ $role->label() }}</dt>
                                <dd>{{ $role->description() }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
