@extends('layouts.guest')

@section('title', 'ورود به پنل مدیریت')

@section('content')
    <div class="login-card">
        <div class="login-card__logo">
            <i class="bi bi-file-earmark-pdf"></i>
        </div>

        <div class="text-center mb-4">
            <h1 class="h4 fw-bold mb-1">ورود به پنل مدیریت</h1>
            <p class="text-secondary small mb-0">
                {{ setting('general.app_name', config('app.name')) }}
            </p>
        </div>

        @if (session('success'))
            <div class="alert alert-success small" role="alert">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger small" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}" autocomplete="off" novalidate>
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">ایمیل</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control ltr @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email') }}" required autofocus
                           dir="ltr" placeholder="you@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control ltr @error('password') is-invalid @enderror"
                           id="password" name="password" required dir="ltr" placeholder="••••••••">
                    <button class="btn btn-outline-secondary" type="button"
                            data-password-toggle="#password" tabindex="-1" aria-label="نمایش رمز عبور">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                       @checked(old('remember'))>
                <label class="form-check-label small" for="remember">مرا به خاطر بسپار</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-left"></i>
                ورود
            </button>
        </form>

        <p class="text-center text-secondary small mt-4 mb-0">
            ورود تنها برای کاربران فعال امکان‌پذیر است.
        </p>
    </div>
@endsection
