@props(['icon' => 'bi-inbox', 'title' => 'موردی برای نمایش نیست', 'text' => null])

<div class="empty-state">
    <i class="bi {{ $icon }}"></i>
    <p class="fw-semibold mb-1">{{ $title }}</p>
    @if ($text)
        <p class="small mb-0">{{ $text }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
