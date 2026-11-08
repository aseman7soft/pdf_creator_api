@extends('layouts.guest')

@section('title', 'صفحه یافت نشد')

@section('content')
    <div class="login-card text-center">
        <div class="login-card__logo">
            <i class="bi bi-compass"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">صفحه یافت نشد</h1>
        <p class="text-secondary mb-4">
            صفحه یا موردی که به دنبال آن بودید وجود ندارد یا حذف شده است.
        </p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="{{ url('/admin') }}" class="btn btn-primary">
                <i class="bi bi-speedometer2"></i> بازگشت به داشبورد
            </a>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>
@endsection
