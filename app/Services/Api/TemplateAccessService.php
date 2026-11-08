<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Exceptions\ApiException;
use App\Models\ApiProject;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centralises the template access rules for the public API.
 *
 * A project sees the active templates it is explicitly allowed to use. When the
 * project has no explicit restriction it may use every active template.
 */
final class TemplateAccessService
{
    /**
     * @throws ApiException 404 TEMPLATE_NOT_FOUND / 422 TEMPLATE_INACTIVE / 403 TEMPLATE_NOT_ALLOWED
     */
    public function findForProject(ApiProject $project, string $slug): Template
    {
        /** @var Template|null $template */
        $template = Template::query()
            ->with('variables')
            ->where('slug', $slug)
            ->first();

        if (! $template instanceof Template) {
            throw ApiException::notFound('قالب مورد نظر یافت نشد.', 'TEMPLATE_NOT_FOUND');
        }

        if (! $template->is_active) {
            throw ApiException::unprocessable('TEMPLATE_INACTIVE', 'قالب مورد نظر غیرفعال است.');
        }

        if (! $project->canUseTemplate($template)) {
            throw ApiException::forbidden(
                'دسترسی این پروژه به قالب مورد نظر مجاز نیست.',
                'TEMPLATE_NOT_ALLOWED',
            );
        }

        return $template;
    }

    /**
     * @return Builder<Template>
     */
    public function queryForProject(ApiProject $project): Builder
    {
        $query = Template::query()->active()->with('variables');

        if ($project->hasTemplateRestrictions()) {
            $query->whereHas(
                'projects',
                fn (Builder $inner): Builder => $inner->whereKey($project->getKey()),
            );
        }

        return $query;
    }

    /**
     * Sample payload built from the declared default values.
     *
     * @return array<string, mixed>
     */
    public function sampleData(Template $template): array
    {
        $template->loadMissing('variables');

        $sample = [];

        foreach ($template->variables as $variable) {
            $sample[$variable->name] = $variable->defaultCasted();
        }

        return $sample;
    }
}
