<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AssetType;
use App\Http\Requests\Admin\AssetUploadRequest;
use App\Models\Asset;
use App\Services\Assets\AssetUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AssetController
{
    public function __construct(private readonly AssetUploadService $assets) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:font,image,css,other'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Asset::query()->with('uploader:id,name');

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('handle', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        $counts = [];

        foreach (AssetType::cases() as $case) {
            $counts[$case->value] = Asset::query()->ofType($case)->count();
        }

        return view('assets.index', [
            'assets' => $query->latest('id')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
            'types' => AssetType::options(),
            'counts' => $counts,
            'maxUploadKb' => $this->assets->maxUploadKb(),
            'allowedExtensions' => AssetUploadService::EXTENSION_WHITELIST,
        ]);
    }

    public function store(AssetUploadRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $type = AssetType::from((string) $validated['type']);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $asset = $this->assets->store(
            $file,
            $type,
            $request->user(),
            $validated['name'] ?? null,
            $validated['description'] ?? null,
        );

        return redirect()
            ->route('admin.assets.index', ['type' => $type->value])
            ->with(
                'success',
                'فایل با دسترسی asset://'.$asset->handle.' ذخیره شد. از همین handle در قالب‌ها استفاده کنید.',
            );
    }

    public function file(Asset $asset): Response|RedirectResponse
    {
        Gate::authorize('view', $asset);

        try {
            $contents = $this->readAsset($asset);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $inline = in_array($asset->type, [AssetType::Image, AssetType::Font], true);

        return response($contents, 200, [
            'Content-Type' => (string) ($asset->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$this->safeFileName($asset).'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function download(Asset $asset): Response|RedirectResponse
    {
        Gate::authorize('view', $asset);

        try {
            $contents = $this->readAsset($asset);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return response($contents, 200, [
            'Content-Type' => (string) ($asset->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => 'attachment; filename="'.$this->safeFileName($asset).'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $handle = $asset->handle;

        try {
            $this->assets->delete($asset);
        } catch (Throwable $exception) {
            return back()->with('error', 'حذف فایل ناموفق بود: '.$exception->getMessage());
        }

        return back()->with('success', 'فایل '.$handle.' حذف شد.');
    }

    /**
     * @throws RuntimeException
     */
    private function readAsset(Asset $asset): string
    {
        $disk = Storage::disk($asset->diskName());
        $path = (string) $asset->path;

        if ($path === '' || ! $disk->exists($path)) {
            throw new RuntimeException('فایل روی دیسک یافت نشد.');
        }

        return (string) $disk->get($path);
    }

    private function safeFileName(Asset $asset): string
    {
        $base = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', (string) ($asset->name ?: $asset->handle)) ?: $asset->handle;

        return trim($base, '-').'.'.$asset->extension;
    }
}
