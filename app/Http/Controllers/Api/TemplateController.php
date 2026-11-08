<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListTemplatesRequest;
use App\Http\Resources\TemplateDetailResource;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\Api\ApiResponseService;
use App\Services\Api\TemplateAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /templates and GET /templates/{slug}.
 */
class TemplateController extends ApiController
{
    public function __construct(private readonly TemplateAccessService $templates) {}

    public function index(ListTemplatesRequest $request): JsonResponse
    {
        return $this->handleApiErrors($request, function () use ($request): JsonResponse {
            $project = $this->project($request);

            $query = $this->templates->queryForProject($project);

            if (($term = $request->searchTerm()) !== null) {
                $query->search($term);
            }

            if (($category = $request->category()) !== null) {
                $query->where('category', $category);
            }

            $paginator = $query
                ->orderBy('name')
                ->paginate($request->perPage(), ['*'], 'page', $request->pageNumber());

            $data = $paginator->getCollection()
                ->map(static fn (Template $template): array => (new TemplateResource($template))->resolve($request))
                ->values()
                ->all();

            return ApiResponseService::success($data, [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]);
        });
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return $this->handleApiErrors($request, function () use ($request, $slug): JsonResponse {
            $project = $this->project($request);

            $template = $this->templates->findForProject($project, $slug);

            $this->attribute($request, 'api_template_id', $template->getKey());

            return ApiResponseService::success(
                (new TemplateDetailResource($template))->resolve($request),
            );
        });
    }
}
