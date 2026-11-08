@php
    $flashMessages = [
        'success' => ['class' => 'alert-success', 'icon' => 'bi-check-circle-fill'],
        'error' => ['class' => 'alert-danger', 'icon' => 'bi-exclamation-octagon-fill'],
        'warning' => ['class' => 'alert-warning', 'icon' => 'bi-exclamation-triangle-fill'],
        'info' => ['class' => 'alert-info', 'icon' => 'bi-info-circle-fill'],
    ];
@endphp

@foreach ($flashMessages as $key => $meta)
    @if (session()->has($key))
        <div class="alert {{ $meta['class'] }} alert-dismissible d-flex align-items-start gap-2 shadow-sm"
             role="alert" data-auto-dismiss="7000">
            <i class="bi {{ $meta['icon'] }} fs-5"></i>
            <div class="flex-grow-1">{!! nl2br(e(session($key))) !!}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible shadow-sm" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <div class="flex-grow-1">
                <strong class="d-block mb-1">خطا در اعتبارسنجی اطلاعات:</strong>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
    </div>
@endif
