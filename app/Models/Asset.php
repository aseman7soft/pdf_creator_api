<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'handle',
        'name',
        'type',
        'mime_type',
        'extension',
        'size',
        'disk',
        'path',
        'checksum',
        'metadata',
        'description',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, AssetType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function diskName(): string
    {
        return $this->disk ?: (string) config('pdf.disk', 'local');
    }

    public function exists(): bool
    {
        return Storage::disk($this->diskName())->exists($this->path);
    }

    public function contents(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        return Storage::disk($this->diskName())->get($this->path);
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->diskName())->path($this->path);
    }

    public function humanFileSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1).' '.$units[$index];
    }

    public function referenceToken(): string
    {
        return 'asset://'.$this->handle;
    }
}
