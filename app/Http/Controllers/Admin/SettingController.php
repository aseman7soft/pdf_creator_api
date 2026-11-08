<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithGenerationEngine;
use App\Http\Requests\Admin\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettingController
{
    use InteractsWithGenerationEngine;

    /**
     * @var array<string, string>
     */
    private const GROUP_LABELS = [
        'general' => 'Ø¹Ù…ÙˆÙ…ÛŒ',
        'documents' => 'Ø§Ø³Ù†Ø§Ø¯',
        'api' => 'API',
        'gotenberg' => 'Gotenberg',
        'security' => 'Ø§Ù…Ù†ÛŒØª',
        'queue' => 'ØµÙ',
    ];

    /**
     * @var array<string, string>
     */
    private const GROUP_ICONS = [
        'general' => 'bi-sliders',
        'documents' => 'bi-file-earmark-pdf',
        'api' => 'bi-braces',
        'gotenberg' => 'bi-printer',
        'security' => 'bi-shield-lock',
        'queue' => 'bi-stack',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Setting::class);

        return view('settings.index', [
            'groups' => $this->settings->grouped(),
            'groupLabels' => self::GROUP_LABELS,
            'groupIcons' => self::GROUP_ICONS,
            'gotenberg' => $this->gotenbergHealth(),
        ]);
    }

    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $defaults = $this->settings->defaults();

        foreach ($defaults as $key => $definition) {
            $raw = data_get($validated, $key);
            $type = (string) $definition['type'];

            $value = match (true) {
                in_array($type, ['boolean', 'bool'], true) => filter_var($raw, FILTER_VALIDATE_BOOL),
                in_array($type, ['integer', 'int'], true) => $raw === null || $raw === '' ? null : (int) $raw,
                in_array($type, ['float', 'decimal'], true) => $raw === null || $raw === '' ? null : (float) $raw,
                $raw === null || $raw === '' => null,
                default => (string) $raw,
            };

            $this->settings->set(
                key: $key,
                value: $value,
                type: $type,
                group: (string) $definition['group'],
                description: (string) $definition['description'],
                isPublic: (bool) $definition['is_public'],
            );
        }

        return back()->with('success', 'ØªÙ†Ø¸ÛŒÙ…Ø§Øª Ø¨Ø§ Ù…ÙˆÙÙ‚ÛŒØª Ø°Ø®ÛŒØ±Ù‡ Ø´Ø¯.');
    }

    public function testGotenberg(): JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', Setting::class);

        $health = $this->gotenbergHealth();

        if (request()->expectsJson()) {
            return response()->json([
                'available' => $health['available'],
                'message' => $health['message'],
                'details' => $health['details'],
            ]);
        }

        return back()->with($health['available'] ? 'success' : 'error', $health['message']);
    }
}
