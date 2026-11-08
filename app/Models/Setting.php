<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function castedValue(): mixed
    {
        return match ($this->type) {
            'integer', 'int' => (int) $this->value,
            'float', 'decimal' => (float) $this->value,
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json', 'array' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * @return list<string>
     */
    public static function knownTypes(): array
    {
        return ['string', 'text', 'integer', 'float', 'boolean', 'json'];
    }
}
