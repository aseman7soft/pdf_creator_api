@extends('layouts.guest')

@section('title', 'دسترسی غیرمجاز')

@section('content')
    <div class="login-card text-center">
        <div class="login-card__logo" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">دسترسی غیرمجاز</h1>
        <p class="text-secondary mb-4">
            شما اجازه دسترسی به این بخش را ندارید. در صورت نیاز با مدیر سیستم تماس بگیرید.
        </p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="{{ url('/admin') }}" class="btn btn-primary">
                <i class="bi bi-speedometer2"></i> بازگشت به داشبورد
            </a>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary">
                <i class="bi bi-house"></i> صفحه اصلی
            </a>
        </div>
    </div>
@endsection
