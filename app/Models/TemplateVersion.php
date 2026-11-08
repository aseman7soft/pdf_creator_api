<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'version',
        'html',
        'css',
        'header_html',
        'footer_html',
        'page_size',
        'orientation',
        'margins',
        'pdf_options',
        'variables',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'margins' => 'array',
            'pdf_options' => 'array',
            'variables' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Variable definitions captured in this version.
     *
     * @return list<array<string, mixed>>
     */
    public function variablesSnapshot(): array
    {
        return is_array($this->variables) ? $this->variables : [];
    }
}
