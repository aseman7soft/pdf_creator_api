<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'api_project_id',
        'name',
        'key_prefix',
        'key_hash',
        'is_active',
        'expires_at',
        'rate_limit_per_minute',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<GeneratedDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
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
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || ! $this->is_active;
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function maskedKey(): string
    {
        return $this->key_prefix.'••••••••';
    }

    public function effectiveRateLimit(): int
    {
        return $this->rate_limit_per_minute
            ?? $this->project?->effectiveRateLimit()
            ?? (int) config('api.rate_limit_per_minute', 60);
    }

    public function statusLabel(): string
    {
        if ($this->isRevoked()) {
            return 'لغو شده';
        }

        if ($this->isExpired()) {
            return 'منقضی شده';
        }

        return 'فعال';
    }

    public function statusBadgeClass(): string
    {
        return $this->isUsable() ? 'text-bg-success' : 'text-bg-secondary';
    }

    public function touchUsage(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
