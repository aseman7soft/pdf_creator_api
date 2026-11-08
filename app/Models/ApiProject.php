<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiProject extends Model
{
    /** @use HasFactory<ApiProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'rate_limit_per_minute',
        'monthly_quota',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'monthly_quota' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return BelongsToMany<Template, $this>
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'api_project_template')->withTimestamps();
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
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function effectiveRateLimit(): int
    {
        return $this->rate_limit_per_minute
            ?? (int) config('api.rate_limit_per_minute', 60);
    }

    /**
     * A project with no template restriction may use every active template.
     */
    public function hasTemplateRestrictions(): bool
    {
        return $this->templates()->exists();
    }

    public function canUseTemplate(Template $template): bool
    {
        if (! $this->hasTemplateRestrictions()) {
            return $template->is_active;
        }

        return $this->templates()->whereKey($template->getKey())->exists();
    }

    public function monthlyUsageCount(): int
    {
        return $this->requestLogs()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function isQuotaExhausted(): bool
    {
        if ($this->monthly_quota === null) {
            return false;
        }

        return $this->monthlyUsageCount() >= $this->monthly_quota;
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'فعال' : 'غیرفعال';
    }

    public function statusBadgeClass(): string
    {
        return $this->is_active ? 'text-bg-success' : 'text-bg-secondary';
    }
}
