<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ApiProjectRequest;
use App\Models\ApiProject;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApiProjectController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ApiProject::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = ApiProject::query()
            ->withCount(['apiKeys', 'documents', 'templates']);

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        return view('projects.index', [
            'projects' => $query->latest('id')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ApiProject::class);

        return view('projects.form', [
            'project' => new ApiProject(['is_active' => true]),
            'mode' => 'create',
            'templates' => $this->availableTemplates(),
            'selectedTemplates' => [],
        ]);
    }

    public function store(ApiProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $project = DB::transaction(function () use ($data): ApiProject {
            $project = ApiProject::query()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $this->nullify($data['description'] ?? null),
                'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL),
                'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
                'monthly_quota' => $data['monthly_quota'] ?? null,
            ]);

            $project->templates()->sync($data['templates'] ?? []);

            return $project;
        });

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('success', __('panel.flash.project_created'));
    }

    public function show(ApiProject $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['apiKeys' => fn ($query) => $query->withTrashed()->orderByDesc('id'), 'templates']);

        $keys = $project->apiKeys()
            ->withTrashed()
            ->orderByDesc('id')
            ->get();

        return view('projects.show', [
            'project' => $project,
            'keys' => $keys,
            'plainKey' => session('plain_key'),
            'plainKeyName' => session('plain_key_name'),
            'stats' => [
                'documents' => $project->documents()->count(),
                'documents_completed' => $project->documents()->completed()->count(),
                'documents_failed' => $project->documents()->failed()->count(),
                'requests_month' => $project->monthlyUsageCount(),
                'last_activity' => $project->last_activity_at,
                'api_keys' => $keys->whereNull('deleted_at')->count(),
                'active_keys' => $keys->filter(fn ($key): bool => $key->isUsable())->count(),
            ],
        ]);
    }

    public function edit(ApiProject $project): View
    {
        Gate::authorize('update', $project);

        $project->load('templates');

        return view('projects.form', [
            'project' => $project,
            'mode' => 'edit',
            'templates' => $this->availableTemplates(),
            'selectedTemplates' => $project->templates->pluck('id')->all(),
        ]);
    }

    public function update(ApiProjectRequest $request, ApiProject $project): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $project): void {
            $project->fill([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $this->nullify($data['description'] ?? null),
                'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL),
                'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
                'monthly_quota' => $data['monthly_quota'] ?? null,
            ])->save();

            $project->templates()->sync($data['templates'] ?? []);
        });

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('success', __('panel.flash.project_updated'));
    }

    public function toggle(ApiProject $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->forceFill(['is_active' => ! $project->is_active])->save();

        return back()->with(
            'success',
            $project->is_active ? 'پروژه فعال شد.' : 'پروژه غیرفعال شد؛ کلیدهای آن نیز از دسترس خارج می‌شوند.',
        );
    }

    public function destroy(ApiProject $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('admin.projects.index')
            ->with('success', __('panel.flash.project_deleted'));
    }

    /**
     * @return Collection<int, Template>
     */
    private function availableTemplates()
    {
        return Template::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active', 'category']);
    }

    private function nullify(mixed $value): ?string
    {
        $value = $value === null ? null : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
