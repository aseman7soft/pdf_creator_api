<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class IdempotencyKey extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'api_project_id',
        'key',
        'request_hash',
        'response_status',
        'generated_document_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<GeneratedDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }
}
