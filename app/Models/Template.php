<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateOrientation;
use App\Enums\TemplatePageSize;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'html',
        'css',
        'header_html',
        'footer_html',
        'is_active',
        'locale',
        'version',
        'page_size',
        'orientation',
        'margins',
        'pdf_options',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'margins' => 'array',
            'pdf_options' => 'array',
            'page_size' => TemplatePageSize::class,
            'orientation' => TemplateOrientation::class,
        ];
    }

    /**
     * @return HasMany<TemplateVariable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version');
    }

    /**
     * @return BelongsToMany<ApiProject, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(ApiProject::class, 'api_project_template')->withTimestamps();
    }

    /**
     * @return HasMany<GeneratedDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
            $inner->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhere('category', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    /**
     * @return array{top: int|float, right: int|float, bottom: int|float, left: int|float}
     */
    public function marginsArray(): array
    {
        $defaults = config('pdf.default_margins', [
            'top' => 12,
            'right' => 12,
            'bottom' => 12,
            'left' => 12,
        ]);

        return array_merge($defaults, is_array($this->margins) ? $this->margins : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfOptionsArray(): array
    {
        return array_merge([
            'print_background' => true,
            'scale' => 1.0,
            'prefer_css_page_size' => true,
            'display_header_footer' => $this->header_html !== null || $this->footer_html !== null,
        ], is_array($this->pdf_options) ? $this->pdf_options : []);
    }

    /**
     * Schema pushed to API consumers and used for validation.
     *
     * @return list<array<string, mixed>>
     */
    public function variablesSchema(): array
    {
        return $this->variables->map(fn (TemplateVariable $variable): array => $variable->schemaEntry())->values()->all();
    }

    /**
     * @return list<string>
     */
    public function variableNames(): array
    {
        return $this->variables->pluck('name')->values()->all();
    }

    /**
     * @return list<string>
     */
    public function requiredVariableNames(): array
    {
        return $this->variables->where('is_required', true)->pluck('name')->values()->all();
    }

    /**
     * Persist a snapshot of the current template state.
     */
    public function snapshotVersion(?User $user = null, ?string $note = null, bool $bumpVersion = true): TemplateVersion
    {
        return DB::transaction(function () use ($user, $note, $bumpVersion): TemplateVersion {
            $this->loadMissing('variables');
            $nextVersion = max(
                (int) $this->version,
                (int) ($this->versions()->max('version') ?? 0),
            );

            if ($bumpVersion) {
                $nextVersion++;
            }

            $version = $this->versions()->create([
                'version' => $nextVersion,
                'html' => $this->html,
                'css' => $this->css,
                'header_html' => $this->header_html,
                'footer_html' => $this->footer_html,
                'page_size' => $this->page_size->value,
                'orientation' => $this->orientation->value,
                'margins' => $this->marginsArray(),
                'pdf_options' => $this->pdfOptionsArray(),
                'variables' => $this->variablesSchema(),
                'note' => $note,
                'created_by' => $user?->getKey(),
            ]);

            if ($bumpVersion) {
                $this->forceFill(['version' => $nextVersion])->save();
            }

            return $version;
        });
    }

    /**
     * Revert the template to a previous version, creating a new audit snapshot.
     */
    public function revertToVersion(TemplateVersion $version, ?User $user = null): void
    {
        if ($version->template_id !== $this->getKey()) {
            throw new \InvalidArgumentException('نسخه انتخاب‌شده متعلق به این قالب نیست.');
        }

        DB::transaction(function () use ($version, $user): void {
            $this->fill([
                'html' => $version->html,
                'css' => $version->css,
                'header_html' => $version->header_html,
                'footer_html' => $version->footer_html,
                'page_size' => $version->page_size,
                'orientation' => $version->orientation,
                'margins' => $version->margins,
                'pdf_options' => $version->pdf_options,
                'updated_by' => $user?->getKey(),
            ]);
            $this->save();

            $this->createNewVersion($user, 'بازگردانی به نسخه '.$version->version);
        });
    }

    /**
     * Save a new version snapshot after editing.
     */
    public function createNewVersion(?User $user = null, ?string $note = null): TemplateVersion
    {
        $this->refresh();
        $this->load('variables');

        return $this->snapshotVersion($user, $note, true);
    }

    public function copyAs(string $name, string $slug, ?User $user = null): self
    {
        $this->loadMissing('variables');

        return DB::transaction(function () use ($name, $slug, $user): self {
            /** @var self $copy */
            $copy = $this->replicate(['created_at', 'updated_at']);
            $copy->name = $name;
            $copy->slug = $slug;
            $copy->version = 1;
            $copy->created_by = $user?->getKey();
            $copy->updated_by = $user?->getKey();
            $copy->save();

            foreach ($this->variables as $variable) {
                $copy->variables()->create($variable->only([
                    'name', 'label', 'type', 'is_required', 'default_value',
                    'description', 'validation_rules', 'min_length', 'max_length',
                    'min_value', 'max_value', 'sort_order',
                ]));
            }

            $copy->load('variables');
            $copy->snapshotVersion($user, 'کپی از قالب '.$this->name, false);

            return $copy;
        });
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
