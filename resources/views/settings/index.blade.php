@extends('layouts.app')

@section('title', 'ØªÙ†Ø¸ÛŒÙ…Ø§Øª')
@section('heading', 'ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ø³ÛŒØ³ØªÙ…')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [['label' => 'ØªÙ†Ø¸ÛŒÙ…Ø§Øª']]])
@endsection

@section('content')
    @php
        $firstGroup = array_key_first($groups);
        $pageSizes = \App\Enums\TemplatePageSize::options();
        $groupDescriptions = [
            'general' => 'Ù†Ø§Ù… Ø³Ø±ÙˆÛŒØ³ØŒ Ø±Ù†Ú¯ Ø¨Ø±Ù†Ø¯ØŒ Ø§ÛŒÙ…ÛŒÙ„ Ù¾Ø´ØªÛŒØ¨Ø§Ù†ÛŒ Ùˆ ØªØ¹Ø¯Ø§Ø¯ Ø±Ø¯ÛŒÙ ØµÙØ­Ø§Øª.',
            'documents' => 'Ù…Ø¯Øª Ù†Ú¯Ù‡Ø¯Ø§Ø±ÛŒ Ø§Ø³Ù†Ø§Ø¯ØŒ Ø§Ù†Ø¯Ø§Ø²Ù‡ Ù¾ÛŒØ´â€ŒÙØ±Ø¶ ØµÙØ­Ù‡ Ùˆ Ù…Ø­Ø¯ÙˆØ¯ÛŒØªâ€ŒÙ‡Ø§ÛŒ ÙˆØ±ÙˆØ¯ÛŒ.',
            'api' => 'Ø³Ù‚Ù Ù†Ø±Ø® Ù¾ÛŒØ´â€ŒÙØ±Ø¶ Ùˆ Ù…Ø¯Øª Ø§Ø¹ØªØ¨Ø§Ø± Ú©Ù„ÛŒØ¯ Idempotency.',
            'gotenberg' => 'Ø¢Ø¯Ø±Ø³ Ùˆ Timeout Ø³Ø±ÙˆÛŒØ³ Gotenberg Ø¨Ø±Ø§ÛŒ ØªÙˆÙ„ÛŒØ¯ PDF.',
            'security' => 'Ø§Ø¹ØªØ¨Ø§Ø±Ø³Ù†Ø¬ÛŒ ØªØµØ§ÙˆÛŒØ± Ø§ÛŒÙ†ØªØ±Ù†ØªÛŒ Ùˆ Ø­Ø¯Ø§Ú©Ø«Ø± Ø­Ø¬Ù… Ø¢Ù¾Ù„ÙˆØ¯ ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ Ø¬Ø§Ù†Ø¨ÛŒ.',
            'queue' => 'Ù†Ø§Ù… ØµÙØŒ ØªØ¹Ø¯Ø§Ø¯ ØªÙ„Ø§Ø´ Ùˆ Ø­Ø¯Ø§Ú©Ø«Ø± Ø²Ù…Ø§Ù† Ø§Ø¬Ø±Ø§ÛŒ JobÙ‡Ø§.',
        ];
    @endphp

    <div class="page-heading">
        <h1>ØªÙ†Ø¸ÛŒÙ…Ø§Øª</h1>
        <span class="badge text-bg-light border">{{ fa_num(count($groups)) }} Ú¯Ø±ÙˆÙ‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs settings-tabs card-header-tabs px-2 pt-2" role="tablist">
                            @foreach ($groups as $group => $settings)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $group === $firstGroup ? 'active' : '' }}"
                                            data-bs-toggle="tab" data-bs-target="#settings-{{ $group }}"
                                            type="button" role="tab">
                                        <i class="bi {{ $groupIcons[$group] ?? 'bi-sliders' }}"></i>
                                        {{ $groupLabels[$group] ?? $group }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card-body">
                        <div class="tab-content">
                            @foreach ($groups as $group => $settings)
                                <div class="tab-pane fade {{ $group === $firstGroup ? 'show active' : '' }}"
                                     id="settings-{{ $group }}" role="tabpanel">
                                    <p class="hint-text">{{ $groupDescriptions[$group] ?? '' }}</p>

                                    @foreach ($settings as $key => $definition)
                                        @php
                                            $type = (string) $definition['type'];
                                            $value = old($key, $definition['value']);
                                            $fieldId = 'setting-'.str_replace('.', '-', $key);
                                            $nameParts = explode('.', $key, 2);
                                            $inputName = count($nameParts) === 2
                                                ? $nameParts[0].'['.$nameParts[1].']'
                                                : $key;
                                        @endphp

                                        <div class="mb-3">
                                            <label class="form-label" for="{{ $fieldId }}">
                                                {{ $definition['description'] ?: $key }}
                                            </label>

                                            @if (in_array($type, ['boolean', 'bool'], true))
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="{{ $inputName }}" value="0">
                                                    <input class="form-check-input" type="checkbox" id="{{ $fieldId }}"
                                                           name="{{ $inputName }}" value="1"
                                                           @checked((bool) filter_var($value, FILTER_VALIDATE_BOOL))>
                                                    <label class="form-check-label" for="{{ $fieldId }}">
                                                        ÙØ¹Ø§Ù„ Ø¨Ø§Ø´Ø¯
                                                    </label>
                                                </div>
                                            @elseif ($key === 'documents.default_format')
                                                <select class="form-select" id="{{ $fieldId }}" name="{{ $inputName }}">
                                                    @foreach ($pageSizes as $size => $label)
                                                        <option value="{{ $size }}" @selected((string) $value === (string) $size)>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @elseif (in_array($type, ['integer', 'int'], true))
                                                <input type="number" class="form-control ltr" id="{{ $fieldId }}"
                                                       name="{{ $inputName }}" value="{{ $value }}" min="0" step="1">
                                            @elseif (in_array($type, ['float', 'decimal'], true))
                                                <input type="number" class="form-control ltr" id="{{ $fieldId }}"
                                                       name="{{ $inputName }}" value="{{ $value }}" min="0" step="any">
                                            @elseif ($key === 'general.brand_color')
                                                <input type="text" class="form-control ltr" id="{{ $fieldId }}"
                                                       name="{{ $inputName }}" value="{{ $value }}" maxlength="7"
                                                       pattern="#[0-9a-fA-F]{6}" placeholder="#0d6efd">
                                            @elseif ($key === 'gotenberg.url')
                                                <input type="url" class="form-control ltr" id="{{ $fieldId }}"
                                                       name="{{ $inputName }}" value="{{ $value }}"
                                                       placeholder="{{ config('pdf.gotenberg.url') }}">
                                                <div class="form-text">
                                                    Ø®Ø§Ù„ÛŒ = Ø§Ø³ØªÙØ§Ø¯Ù‡ Ø§Ø² Ù…Ù‚Ø¯Ø§Ø± ÙØ§ÛŒÙ„ <code>.env</code>
                                                    (<code>{{ config('pdf.gotenberg.url') }}</code>)
                                                </div>
                                            @else
                                                <input type="text" class="form-control {{ str_contains($key, 'email') || str_contains($key, 'url') ? 'ltr' : '' }}"
                                                       id="{{ $fieldId }}" name="{{ $inputName }}" value="{{ $value }}"
                                                       maxlength="1000">
                                            @endif

                                            <div class="form-text">
                                                Ú©Ù„ÛŒØ¯: <code>{{ $key }}</code>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª
                        </button>
                        <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Ø¨Ø§Ø²Ù†Ø´Ø§Ù†ÛŒ ÙØ±Ù…
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title">Ø§ØªØµØ§Ù„ Gotenberg</span>
                    @if ($gotenberg['available'])
                        <span class="badge text-bg-success">Ø¯Ø± Ø¯Ø³ØªØ±Ø³</span>
                    @else
                        <span class="badge text-bg-danger">Ø¯Ø± Ø¯Ø³ØªØ±Ø³ Ù†ÛŒØ³Øª</span>
                    @endif
                </div>
                <div class="card-body">
                    <p>{{ $gotenberg['message'] }}</p>
                    <p class="hint-text mb-2">
                        Ø¢Ø¯Ø±Ø³ ÙØ¹Ù„ÛŒ: <code class="ltr">{{ config('pdf.gotenberg.url') }}</code>
                    </p>

                    @if (! empty($gotenberg['details']))
                        <dl class="kv-list mb-2">
                            @foreach ($gotenberg['details'] as $key => $value)
                                @if (is_scalar($value) && ! is_bool($value))
                                    <dt>{{ $key }}</dt>
                                    <dd class="ltr">{{ $value }}</dd>
                                @endif
                            @endforeach
                        </dl>
                    @endif

                    <form method="POST" action="{{ route('admin.settings.test-gotenberg') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-plug"></i> ØªØ³Øª Ø§ØªØµØ§Ù„ Gotenberg
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header card-title">Ø±Ø§Ù‡Ù†Ù…Ø§</div>
                <div class="card-body">
                    <ul class="small mb-0 ps-3">
                        <li>Ù…Ù‚Ø§Ø¯ÛŒØ± Ø§ÛŒÙ† ØµÙØ­Ù‡ Ø¯Ø± Ø¬Ø¯ÙˆÙ„ <code>settings</code> Ø°Ø®ÛŒØ±Ù‡ Ùˆ Ú©Ø´ Ù…ÛŒâ€ŒØ´ÙˆÙ†Ø¯.</li>
                        <li>Ú©Ù„ÛŒØ¯Ù‡Ø§ÛŒ Ø§Ù…Ù†ÛŒØªÛŒ (Ù…Ø§Ù†Ù†Ø¯ Ú©Ù„ÛŒØ¯ API) Ø¯Ø± Ø§ÛŒÙ† ØµÙØ­Ù‡ Ù†Ù…Ø§ÛŒØ´ Ø¯Ø§Ø¯Ù‡ Ù†Ù…ÛŒâ€ŒØ´ÙˆÙ†Ø¯.</li>
                        <li>ØªØºÛŒÛŒØ±Ø§Øª Ù¾Ø³ Ø§Ø² Ø°Ø®ÛŒØ±Ù‡ØŒ Ø¨Ù„Ø§ÙØ§ØµÙ„Ù‡ Ø¯Ø± Ù¾Ù†Ù„ Ùˆ Ø³Ø±ÙˆÛŒØ³ Ø§Ø¹Ù…Ø§Ù„ Ù…ÛŒâ€ŒØ´ÙˆÙ†Ø¯.</li>
                        @if (session('success'))
                            <li class="text-success">Ø¢Ø®Ø±ÛŒÙ† Ø°Ø®ÛŒØ±Ù‡ Ù…ÙˆÙÙ‚ Ø¨ÙˆØ¯Ù‡ Ø§Ø³Øª.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
