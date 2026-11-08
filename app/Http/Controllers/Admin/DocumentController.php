<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use App\Http\Controllers\Admin\Concerns\InteractsWithGenerationEngine;
use App\Models\ApiProject;
use App\Models\GeneratedDocument;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DocumentController
{
    use InteractsWithGenerationEngine;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GeneratedDocument::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:pending,processing,completed,failed'],
            'project_id' => ['nullable', 'integer', 'exists:api_projects,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = GeneratedDocument::query()->with(['project:id,name', 'template' => fn ($builder) => $builder->withTrashed()]);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('api_project_id', $filters['project_id']);
        }

        if (! empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        // Gregorian date inputs are interpreted in the application timezone
        // (Asia/Tehran) and rendered as Jalali dates in the panel.
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $this->tehranDate($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $this->tehranDate($filters['date_to'])->endOfDay());
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        return view('documents.index', [
            'documents' => $query->latest('id')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
            'projects' => ApiProject::query()->orderBy('name')->get(['id', 'name']),
            'templates' => Template::query()->withTrashed()->orderBy('name')->get(['id', 'name']),
            'statuses' => DocumentStatus::options(),
        ]);
    }

    public function show(GeneratedDocument $document): View
    {
        Gate::authorize('view', $document);

        $document->load([
            'project',
            'apiKey',
            'template' => fn ($builder) => $builder->withTrashed(),
            'documentJobs',
        ]);

        $ttl = (int) setting('documents.signed_url_ttl', config('pdf.signed_url_ttl', 30));

        return view('documents.show', [
            'document' => $document,
            'signedUrl' => URL::temporarySignedRoute(
                'admin.documents.download.signed',
                now()->addMinutes(max(1, $ttl)),
                ['document' => $document->getKey()],
            ),
            'requestLogs' => $document->requestLogs()->latest('id')->limit(20)->get(),
            'modeLabels' => GenerationMode::cases(),
        ]);
    }

    public function download(GeneratedDocument $document): Response|RedirectResponse
    {
        Gate::authorize('download', $document);

        try {
            return $this->documentDownloadResponse($document);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function downloadSigned(GeneratedDocument $document): Response|RedirectResponse
    {
        Gate::authorize('view', $document);

        try {
            return $this->documentDownloadResponse($document, true);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function retry(GeneratedDocument $document): RedirectResponse
    {
        Gate::authorize('retry', $document);

        if ($document->status !== DocumentStatus::Failed) {
            return back()->with('error', 'فقط اسناد ناموفق قابل تولید مجدد هستند.');
        }

        try {
            $this->retryDocument($document);
        } catch (Throwable $exception) {
            return back()->with('error', 'تولید مجدد انجام نشد: '.$exception->getMessage());
        }

        return back()->with('success', __('panel.flash.document_retried'));
    }

    public function destroy(GeneratedDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $this->deleteDocumentFile($document);

        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', __('panel.flash.document_deleted'));
    }

    private function deleteDocumentFile(GeneratedDocument $document): void
    {
        $path = (string) ($document->file_path ?? '');

        if ($path === '') {
            return;
        }

        try {
            $storageClass = static::DOCUMENT_STORAGE_SERVICE;

            if (class_exists($storageClass)) {
                $storage = app($storageClass);

                if (method_exists($storage, 'delete')) {
                    $storage->delete($document);

                    return;
                }
            }

            $disk = Storage::disk((string) config('pdf.disk', 'local'));

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable) {
            // A missing or locked file must not block removing the record.
        }
    }

    private function tehranDate(string $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, (string) config('app.timezone'))->startOfDay();
    }
}
