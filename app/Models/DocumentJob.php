<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DocumentJob extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'generated_document_id',
        'queue',
        'status',
        'attempts',
        'max_attempts',
        'available_at',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_code',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'available_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GeneratedDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', JobStatus::Pending->value);
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => JobStatus::Processing,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markCompleted(?int $durationMs = null): void
    {
        $finishedAt = now();

        $this->forceFill([
            'status' => JobStatus::Completed,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs ?? $this->started_at?->diffInMilliseconds($finishedAt),
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $code, string $message): void
    {
        $finishedAt = now();

        $this->forceFill([
            'status' => JobStatus::Failed,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->started_at?->diffInMilliseconds($finishedAt),
            'error_code' => $code,
            'error_message' => $message,
        ])->save();
    }

    public function durationMs(): ?int
    {
        if ($this->started_at instanceof Carbon && $this->finished_at instanceof Carbon) {
            return (int) $this->started_at->diffInMilliseconds($this->finished_at);
        }

        return $this->duration_ms;
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [JobStatus::Failed, JobStatus::Pending], true)
            && $this->attempts < $this->max_attempts;
    }
}
