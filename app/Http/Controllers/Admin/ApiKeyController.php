<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ApiKeyRequest;
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Services\Api\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ApiKeyController
{
    public function __construct(private readonly ApiKeyService $apiKeys) {}

    public function store(ApiKeyRequest $request, ApiProject $project): RedirectResponse
    {
        $result = $this->apiKeys->create(
            $project,
            (string) $request->string('name'),
            $request->user(),
            $this->parseExpiry($request->input('expires_at')),
            $this->parseRateLimit($request->input('rate_limit_per_minute')),
        );

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('success', 'کلید API جدید ساخته شد. کلید کامل فقط همین یک بار نمایش داده می‌شود.')
            ->with('plain_key', $result['plain'])
            ->with('plain_key_name', $result['key']->name);
    }

    public function rotate(Request $request, ApiProject $project, ApiKey $apiKey): RedirectResponse
    {
        Gate::authorize('rotate', $apiKey);

        abort_unless($apiKey->api_project_id === $project->getKey(), 404);

        if ($apiKey->isRevoked() && $apiKey->trashed()) {
            return back()->with('error', 'کلید حذف‌شده قابل چرخش نیست.');
        }

        $result = $this->apiKeys->rotate($apiKey, $request->user());

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('success', 'کلید قبلی لغو و کلید جدید صادر شد. کلید جدید فقط یک بار نمایش داده می‌شود.')
            ->with('plain_key', $result['plain'])
            ->with('plain_key_name', $result['key']->name);
    }

    public function revoke(ApiProject $project, ApiKey $apiKey): RedirectResponse
    {
        Gate::authorize('update', $apiKey);

        abort_unless($apiKey->api_project_id === $project->getKey(), 404);

        if ($apiKey->isRevoked()) {
            return back()->with('error', 'این کلید قبلاً لغو شده است.');
        }

        $this->apiKeys->revoke($apiKey);

        return back()->with('success', 'کلید API لغو شد.');
    }

    public function destroy(ApiProject $project, ApiKey $apiKey): RedirectResponse
    {
        Gate::authorize('delete', $apiKey);

        abort_unless($apiKey->api_project_id === $project->getKey(), 404);

        $apiKey->forceFill([
            'is_active' => false,
            'revoked_at' => $apiKey->revoked_at ?? now(),
        ])->save();

        $apiKey->delete();

        return back()->with('success', 'کلید API حذف شد.');
    }

    private function parseExpiry(mixed $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, (string) config('app.timezone'))->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseRateLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
