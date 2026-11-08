<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JobStatus;
use App\Http\Controllers\Admin\Concerns\InteractsWithGenerationEngine;
use App\Models\DocumentJob;
use App\Models\GeneratedDocument;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class QueueController
{
    use InteractsWithGenerationEngine;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DocumentJob::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,processing,completed,failed'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = DocumentJob::query()->with([
            'document' => fn ($builder) => $builder->withTrashed(),
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($inner) use ($term): void {
                $inner->where('id', $term)
                    ->orWhere('error_code', 'like', '%'.$term.'%')
                    ->orWhere('error_message', 'like', '%'.$term.'%');
            });
        }

        $perPage = (int) ($filters['per_page'] ?? setting('general.items_per_page', 20));

        return view('queue.index', [
            'jobs' => $query->latest('id')->paginate(max(5, min(100, $perPage)))->withQueryString(),
            'filters' => $filters,
            'statuses' => JobStatus::options(),
            'statusCounts' => $this->statusCounts(),
            'queueDepth' => $this->queueDepth(),
            'databaseJobs' => $this->databaseJobs(),
            'failedJobs' => $this->failedJobs(),
            'failedJobsCount' => $this->countTable('failed_jobs'),
            'hasJobsTable' => $this->tableExists('jobs'),
        ]);
    }

    public function retry(DocumentJob $job): RedirectResponse
    {
        Gate::authorize('retry', $job);

        $document = $job->document()->withTrashed()->first();

        if (! $document instanceof GeneratedDocument) {
            return back()->with('error', 'سند مرتبط با این Job یافت نشد.');
        }

        try {
            $this->retryDocument($document);
        } catch (Throwable $exception) {
            return back()->with('error', 'تلاش مجدد انجام نشد: '.$exception->getMessage());
        }

        $job->forceFill([
            'status' => JobStatus::Pending,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
            'available_at' => now(),
        ])->save();

        return back()->with('success', 'Job برای اجرای مجدد در صف قرار گرفت.');
    }

    public function destroy(DocumentJob $job): RedirectResponse
    {
        Gate::authorize('delete', $job);

        $job->delete();

        return back()->with('success', 'ردیف Job حذف شد.');
    }

    public function retryFailed(string $uuid): RedirectResponse
    {
        $this->authorizeAdmin();

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $exception) {
            return back()->with('error', 'اجرای دستور queue:retry ناموفق بود: '.$exception->getMessage());
        }

        return back()->with('success', 'Job ناموفق به صف بازگردانده شد.');
    }

    public function forgetFailed(string $uuid): RedirectResponse
    {
        $this->authorizeAdmin();

        try {
            Artisan::call('queue:forget', ['id' => $uuid]);
        } catch (Throwable $exception) {
            return back()->with('error', 'حذف Job ناموفق انجام نشد: '.$exception->getMessage());
        }

        return back()->with('success', 'Job ناموفق حذف شد.');
    }

    public function flushFailed(): RedirectResponse
    {
        $this->authorizeAdmin();

        try {
            Artisan::call('queue:flush');
        } catch (Throwable $exception) {
            return back()->with('error', 'پاک‌سازی Jobهای ناموفق انجام نشد: '.$exception->getMessage());
        }

        return back()->with('success', 'همه Jobهای ناموفق حذف شدند.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->isAdmin() === true, 403);
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(JobStatus::options()), 0);

        try {
            $rows = DocumentJob::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            foreach ($rows as $status => $total) {
                $value = $status instanceof JobStatus ? $status->value : (string) $status;
                $counts[$value] = (int) $total;
            }
        } catch (QueryException) {
            // table missing
        }

        return $counts;
    }

    private function queueDepth(): ?int
    {
        return $this->countTable('jobs');
    }

    /**
     * @return Collection<int, object>
     */
    private function databaseJobs()
    {
        if (! $this->tableExists('jobs')) {
            return collect();
        }

        try {
            return DB::table('jobs')
                ->orderBy('id')
                ->limit(10)
                ->get(['id', 'queue', 'attempts', 'reserved_at', 'available_at', 'created_at']);
        } catch (QueryException) {
            return collect();
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function failedJobs()
    {
        if (! $this->tableExists('failed_jobs')) {
            return collect();
        }

        try {
            return DB::table('failed_jobs')->orderByDesc('id')->limit(20)->get();
        } catch (QueryException) {
            return collect();
        }
    }

    private function countTable(string $table): ?int
    {
        if (! $this->tableExists($table)) {
            return null;
        }

        try {
            return (int) DB::table($table)->count();
        } catch (QueryException) {
            return null;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
