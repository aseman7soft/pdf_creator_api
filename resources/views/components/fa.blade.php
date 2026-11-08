@props(['value' => null, 'fallback' => '—', 'decimals' => null])

@if ($value === null || $value === '')
    {{ $fallback }}
@else
    {{ fa_num($decimals !== null ? number_format((float) $value, (int) $decimals) : (string) $value) }}
@endif
