<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\GenerationMode;
use Database\Factories\GeneratedDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GeneratedDocument extends Model
{
    /** @use HasFactory<GeneratedDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'reference',
        'api_project_id',
        'api_key_id',
        'template_id',
        'template_name',
        'template_version',
        'generation_mode',
        'status',
        'file_path',
        'file_size',
        'mime_type',
        'page_count',
        'pdf_meta',
        'payload',
        'options',
        'error_code',
        'error_message',
        'expires_at',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation_mode' => GenerationMode::class,
            'status' => DocumentStatus::class,
            'file_size' => 'integer',
            'page_count' => 'integer',
            'pdf_meta' => 'array',
            'payload' => 'encrypted:array',
            'options' => 'array',
            'expires_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            if ($document->ulid === null) {
                $document->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<ApiProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ApiProject::class, 'api_project_id');
    }

    /**
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<DocumentJob, $this>
     */
    public function documentJobs(): HasMany
    {
        return $this->hasMany(DocumentJob::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<DocumentJob, $this>
     */
    public function latestJob(): HasOne
    {
        return $this->hasOne(DocumentJob::class)->latestOfMany();
    }

    /**
     * @return HasMany<ApiRequestLog, $this>
     */
    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Completed->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Failed->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('api_project_id', $projectId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('reference', 'like', $term)
                ->orWhere('ulid', 'like', $term)
                ->orWhere('template_name', 'like', $term);
        });
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Processing,
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function markCompleted(string $filePath, int $fileSize, array $meta = []): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Completed,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => 'application/pdf',
            'pdf_meta' => $meta,
            'error_code' => null,
            'error_message' => null,
            'generated_at' => now(),
        ])->save();
    }

    public function markFailed(string $code, string $message): void
    {
        $this->forceFill([
            'status' => DocumentStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'generated_at' => now(),
        ])->save();
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function canDownload(): bool
    {
        return $this->status === DocumentStatus::Completed
            && is_string($this->file_path)
            && $this->file_path !== ''
            && ! $this->isExpired();
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function humanFileSize(): string
    {
        if (! $this->file_size) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1).' '.$units[$index];
    }

    public function downloadFileName(): string
    {
        $base = $this->reference ?: $this->template_name ?: 'document';
        $base = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', (string) $base) ?: 'document';

        return trim($base, '-').'.pdf';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
