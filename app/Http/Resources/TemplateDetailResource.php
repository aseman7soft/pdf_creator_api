<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use App\Services\Api\TemplateAccessService;
use Illuminate\Http\Request;

/**
 * Template detail: the public fields plus a ready-to-edit sample payload built
 * from the declared variable defaults.
 *
 * @mixin Template
 */
class TemplateDetailResource extends TemplateResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Template $template */
        $template = $this->resource;

        $data = parent::toArray($request);
        $data['sample_data'] = app(TemplateAccessService::class)->sampleData($template);

        return $data;
    }
}
