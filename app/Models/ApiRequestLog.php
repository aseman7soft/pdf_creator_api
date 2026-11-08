<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'api_project_id',
        'api_key_id',
        'template_id',
        'generated_document_id',
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'request_bytes',
        'response_bytes',
        'ip_address',
        'user_agent',
        'error_code',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'request_bytes' => 'integer',
            'response_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
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
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function statusBadgeClass(): string
    {
        return match (true) {
            $this->status_code >= 500 => 'text-bg-danger',
            $this->status_code >= 400 => 'text-bg-warning',
            default => 'text-bg-success',
        };
    }
}
