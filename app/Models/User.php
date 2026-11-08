<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function createdApiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'created_by');
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function createdTemplates(): HasMany
    {
        return $this->hasMany(Template::class, 'created_by');
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function uploadedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'uploaded_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canManage(): bool
    {
        return $this->role->canManage();
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return $initials !== '' ? $initials : '?';
    }

    public function lastLoginLabel(): string
    {
        if (! $this->last_login_at instanceof Carbon) {
            return 'هرگز';
        }

        return $this->last_login_at->diffForHumans();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
