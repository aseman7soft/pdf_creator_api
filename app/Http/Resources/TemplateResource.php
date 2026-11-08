<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a template. HTML/CSS source is never exposed.
 *
 * @mixin Template
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Template $template */
        $template = $this->resource;

        return [
            'slug' => $template->slug,
            'name' => $template->name,
            'category' => $template->category,
            'description' => $template->description,
            'locale' => $template->locale,
            'version' => $template->version,
            'page_size' => $template->page_size?->value,
            'orientation' => $template->orientation?->value,
            'margins' => $template->marginsArray(),
            'variables' => $template->variables
                ->map(static fn ($variable): array => $variable->schemaEntry())
                ->values()
                ->all(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
