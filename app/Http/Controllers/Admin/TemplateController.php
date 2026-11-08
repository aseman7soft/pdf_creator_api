<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateOrientation;
use App\Enums\TemplatePageSize;
use App\Enums\TemplateVariableType;
use App\Http\Controllers\Admin\Concerns\InteractsWithGenerationEngine;
use App\Http\Requests\Admin\TemplatePreviewRequest;
use App\Http\Requests\Admin\TemplateRequest;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\TemplateVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TemplateController
{
    use InteractsWithGenerationEngine;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Template::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Template::query()->withCount('variables')->withCount('documents');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));
        $perPage = max(5, min(100, $perPage));

        $categories = Template::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        return view('templates.index', [
            'templates' => $query->latest('id')->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Template::class);

        return view('templates.form', [
            'template' => new Template([
                'locale' => 'fa',
                'page_size' => TemplatePageSize::A4,
                'orientation' => TemplateOrientation::Portrait,
                'is_active' => true,
                'margins' => config('pdf.default_margins'),
                'pdf_options' => ['print_background' => true, 'scale' => 1.0, 'prefer_css_page_size' => true],
            ]),
            'mode' => 'create',
            'variables' => collect(),
            'categories' => $this->categories(),
            'version' => null,
        ]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $payload = $this->templatePayload($request);

        $template = DB::transaction(function () use ($payload, $request, $user): Template {
            $template = Template::query()->create($payload + [
                'version' => 1,
                'created_by' => $user?->getKey(),
                'updated_by' => $user?->getKey(),
            ]);

            $this->syncVariables($template, (array) $request->input('variables', []));

            $template->refresh();
            $template->load('variables');
            $template->snapshotVersion($user, 'نسخه اولیه ایجاد قالب', false);

            return $template;
        });

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('success', __('panel.flash.template_created'));
    }

    public function edit(Template $template): View
    {
        Gate::authorize('update', $template);

        $template->load('variables');

        return view('templates.form', [
            'template' => $template,
            'mode' => 'edit',
            'variables' => $template->variables,
            'categories' => $this->categories(),
            'version' => $template->versions()->latest('version')->first(),
        ]);
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        $user = $request->user();
        $payload = $this->templatePayload($request);

        $versioned = DB::transaction(function () use ($payload, $request, $user, $template): bool {
            $template->load('variables');
            $before = $this->contentState($template);

            $template->fill($payload);
            $template->save();

            $this->syncVariables($template, (array) $request->input('variables', []));

            $template->refresh();
            $template->load('variables');
            $after = $this->contentState($template);
            $changed = $before !== $after;

            if ($changed) {
                $note = trim((string) $request->input('version_note', ''));
                $template->createNewVersion(
                    $user,
                    $note !== '' ? $note : 'ویرایش از پنل مدیریت',
                );
                $template->forceFill(['updated_by' => $user?->getKey()])->save();
            } else {
                $template->forceFill(['updated_by' => $user?->getKey()])->save();
            }

            return $changed;
        });

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with(
                'success',
                $versioned
                    ? __('panel.flash.template_updated').' نسخه '.$template->refresh()->version.' ثبت شد.'
                    : __('panel.flash.template_updated').' محتوای قالب تغییری نداشت؛ نسخه جدید ساخته نشد.',
            );
    }

    public function toggle(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('update', $template);

        $template->forceFill([
            'is_active' => ! $template->is_active,
            'updated_by' => $request->user()?->getKey(),
        ])->save();

        return back()->with(
            'success',
            $template->is_active ? 'قالب فعال شد.' : 'قالب غیرفعال شد.',
        );
    }

    public function copy(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('create', Template::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:templates,slug',
            ],
        ], [
            'slug.regex' => 'اسلاگ فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
            'slug.unique' => 'این اسلاگ قبلاً استفاده شده است.',
        ]);

        $copy = $template->copyAs($validated['name'], $validated['slug'], $request->user());

        return redirect()
            ->route('admin.templates.edit', $copy)
            ->with('success', __('panel.flash.template_copied'));
    }

    public function destroy(Template $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $template->delete();

        return redirect()
            ->route('admin.templates.index')
            ->with('success', __('panel.flash.template_deleted'));
    }

    public function versions(Template $template): View
    {
        Gate::authorize('view', $template);

        return view('templates.versions', [
            'template' => $template,
            'versions' => $template->versions()->with('creator:id,name')->paginate(15),
        ]);
    }

    public function showVersion(Template $template, TemplateVersion $version): View
    {
        Gate::authorize('view', $template);

        abort_unless($version->template_id === $template->getKey(), 404);

        return view('templates.version', [
            'template' => $template,
            'version' => $version,
            'variables' => $version->variablesSnapshot(),
        ]);
    }

    public function revertVersion(Request $request, Template $template, TemplateVersion $version): RedirectResponse
    {
        Gate::authorize('update', $template);

        abort_unless($version->template_id === $template->getKey(), 404);

        try {
            $template->revertToVersion($version, $request->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('success', __('panel.flash.template_reverted').' نسخه '.$template->refresh()->version.' ثبت شد.');
    }

    public function preview(Template $template): View
    {
        Gate::authorize('preview', $template);

        $template->load('variables');
        $sample = $this->sampleData($template);

        return view('templates.preview', [
            'template' => $template,
            'variables' => $template->variables,
            'sampleJson' => json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    public function previewHtml(TemplatePreviewRequest $request, Template $template): Response
    {
        $data = $this->decodePreviewData($request);

        if ($data === null) {
            return response()
                ->view('templates.preview-frame', [
                    'warning' => 'داده‌های JSON وارد شده معتبر نیست. لطفاً ساختار JSON را اصلاح کنید.',
                    'source' => (string) $template->html,
                ], 200);
        }

        $result = $this->renderPreviewHtml($template, $data);

        return response()->view('templates.preview-frame', [
            'warning' => $result['warning'],
            'html' => $result['html'],
            'source' => (string) $template->html,
        ], 200);
    }

    public function previewPdf(TemplatePreviewRequest $request, Template $template): Response|RedirectResponse
    {
        $data = $this->decodePreviewData($request);

        if ($data === null) {
            return back()->with('error', 'داده‌های JSON وارد شده معتبر نیست. لطفاً ساختار JSON را اصلاح کنید.');
        }

        try {
            $result = $this->renderPreviewPdf($template, $data);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput()
                ->with('error', 'داده‌های نمونه با قواعد متغیرهای قالب همخوانی ندارد؛ جزئیات خطا در بالای فرم نمایش داده شد.');
        } catch (\Throwable $exception) {
            return back()->with(
                'error',
                'پیش‌نمایش PDF تولید نشد: '.$exception->getMessage(),
            );
        }

        return response($result['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-'.$template->slug.'.pdf"',
            'Content-Length' => (string) $result['size'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function categories()
    {
        return Template::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(TemplateRequest $request): array
    {
        $data = $request->validated();

        $margins = [
            'top' => (float) ($data['margins']['top'] ?? config('pdf.default_margins.top', 12)),
            'right' => (float) ($data['margins']['right'] ?? config('pdf.default_margins.right', 12)),
            'bottom' => (float) ($data['margins']['bottom'] ?? config('pdf.default_margins.bottom', 12)),
            'left' => (float) ($data['margins']['left'] ?? config('pdf.default_margins.left', 12)),
        ];

        $options = [
            'print_background' => filter_var($data['pdf_options']['print_background'] ?? false, FILTER_VALIDATE_BOOL),
            'prefer_css_page_size' => filter_var($data['pdf_options']['prefer_css_page_size'] ?? false, FILTER_VALIDATE_BOOL),
            'display_header_footer' => filter_var($data['pdf_options']['display_header_footer'] ?? false, FILTER_VALIDATE_BOOL),
            'scale' => (float) ($data['pdf_options']['scale'] ?? 1.0),
        ];

        if (($data['page_size'] ?? '') === TemplatePageSize::Custom->value) {
            if (! empty($data['pdf_options']['custom_width'])) {
                $options['width'] = (float) $data['pdf_options']['custom_width'];
            }

            if (! empty($data['pdf_options']['custom_height'])) {
                $options['height'] = (float) $data['pdf_options']['custom_height'];
            }
        }

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'category' => $data['category'],
            'description' => $this->nullify($data['description'] ?? null),
            'locale' => $data['locale'] ?? 'fa',
            'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL),
            'html' => $data['html'],
            'css' => $this->nullify($data['css'] ?? null),
            'header_html' => $this->nullify($data['header_html'] ?? null),
            'footer_html' => $this->nullify($data['footer_html'] ?? null),
            'page_size' => $data['page_size'],
            'orientation' => $data['orientation'],
            'margins' => $margins,
            'pdf_options' => $options,
        ];
    }

    /**
     * Persist template variables transaction-safely.
     *
     * @param  array<int|string, mixed>  $rows
     */
    private function syncVariables(Template $template, array $rows): void
    {
        /** @var Collection<string, TemplateVariable> $existing */
        $existing = $template->variables()->get()->keyBy('name');
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            $payload = [
                'label' => $label !== '' ? $label : $name,
                'type' => (string) ($row['type'] ?? TemplateVariableType::String->value),
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'default_value' => $this->nullify($row['default_value'] ?? null),
                'description' => $this->nullify($row['description'] ?? null),
                'validation_rules' => $this->nullify($row['validation_rules'] ?? null),
                'min_length' => $this->nullifyInt($row['min_length'] ?? null),
                'max_length' => $this->nullifyInt($row['max_length'] ?? null),
                'min_value' => $this->nullifyFloat($row['min_value'] ?? null),
                'max_value' => $this->nullifyFloat($row['max_value'] ?? null),
                'sort_order' => $this->nullifyInt($row['sort_order'] ?? null) ?? ($index + 1),
            ];

            if ($existing->has($name)) {
                $existing->get($name)->fill($payload)->save();
            } else {
                $template->variables()->create($payload + ['name' => $name]);
            }

            $keep[] = $name;
        }

        $template->variables()->whereNotIn('name', $keep)->delete();
        $template->unsetRelation('variables');
    }

    /**
     * Canonical content snapshot used to decide whether a new version is due.
     */
    private function contentState(Template $template): string
    {
        $template->loadMissing('variables');

        $variables = $template->variables
            ->map(fn (TemplateVariable $variable): array => [
                'name' => $variable->name,
                'label' => $variable->label,
                'type' => $variable->type->value,
                'is_required' => (bool) $variable->is_required,
                'default_value' => $variable->default_value,
                'description' => $variable->description,
                'validation_rules' => $variable->validation_rules,
                'min_length' => $variable->min_length,
                'max_length' => $variable->max_length,
                'min_value' => $variable->min_value,
                'max_value' => $variable->max_value,
                'sort_order' => $variable->sort_order,
            ])
            ->values()
            ->all();

        $margins = $template->marginsArray();
        ksort($margins);
        $margins = array_map(static fn (mixed $value): float => (float) $value, $margins);

        $options = $template->pdfOptionsArray();
        ksort($options);
        $options = array_map(static function (mixed $value): mixed {
            return is_bool($value) ? $value : (is_numeric($value) ? (float) $value : $value);
        }, $options);

        return hash('sha256', (string) json_encode([
            'html' => (string) $template->html,
            'css' => (string) $template->css,
            'header_html' => (string) $template->header_html,
            'footer_html' => (string) $template->footer_html,
            'page_size' => $template->page_size->value,
            'orientation' => $template->orientation->value,
            'margins' => $margins,
            'pdf_options' => $options,
            'variables' => $variables,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleData(Template $template): array
    {
        $data = [];

        foreach ($template->variables as $variable) {
            $default = $variable->defaultCasted();

            if ($default !== null) {
                $data[$variable->name] = $default;

                continue;
            }

            $data[$variable->name] = match ($variable->type) {
                TemplateVariableType::Integer => 1,
                TemplateVariableType::Decimal => 1000.5,
                TemplateVariableType::Boolean => true,
                TemplateVariableType::Date => now()->toDateString(),
                TemplateVariableType::DateTime => now()->toDateTimeString(),
                TemplateVariableType::Array => [$this->sampleArrayRow($variable->name)],
                TemplateVariableType::Object => ['title' => 'نمونه', 'value' => 'مقدار'],
                TemplateVariableType::Image => '',
                TemplateVariableType::Url => 'https://example.com',
                TemplateVariableType::Text => 'متن نمونه برای '.$variable->label,
                default => 'نمونه '.$variable->label,
            };
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleArrayRow(string $name): array
    {
        return match ($name) {
            'guests' => ['name' => 'علی رضایی', 'passport' => 'A12345678'],
            'items' => ['description' => 'خدمات نمونه', 'quantity' => 2, 'unit_price' => 500000, 'amount' => 1000000],
            'days' => [
                'day_number' => 1,
                'title' => 'روز اول',
                'description' => 'بازدید از جاذبه‌های گردشگری',
                'meals' => 'صبحانه، ناهار',
                'hotel' => 'هتل نمونه',
            ],
            default => ['title' => 'آیتم نمونه', 'description' => 'توضیح نمونه'],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePreviewData(TemplatePreviewRequest $request): ?array
    {
        $raw = trim((string) ($request->input('data') ?? ''));

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function nullify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullifyInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullifyFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
