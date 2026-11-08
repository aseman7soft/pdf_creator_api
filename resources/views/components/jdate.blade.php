@props(['date' => null, 'format' => 'Y/m/d', 'fallback' => '—'])

{{ $date === null || $date === '' ? $fallback : fa_num(jdate($date, $format)) }}
