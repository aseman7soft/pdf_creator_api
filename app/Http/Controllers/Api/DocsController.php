<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use Illuminate\Http\Response;

/**
 * Swagger UI page and the raw OpenAPI contract. Both are public.
 */
class DocsController extends ApiController
{
    public function docs(): Response
    {
        return $this->handleApiErrors(request(), fn (): Response => response()->view('api.docs', [
            'specUrl' => url('/api/v1/openapi.yaml'),
            'serviceName' => 'سرویس مرکزی تولید اسناد — API v1',
        ], 200, ['Content-Type' => 'text/html; charset=UTF-8']));
    }

    public function openapi(): Response
    {
        return $this->handleApiErrors(request(), function (): Response {
            $path = base_path('docs/openapi.yaml');

            if (! is_file($path)) {
                throw ApiException::notFound('فایل مستندات OpenAPI یافت نشد.', 'SPEC_NOT_FOUND');
            }

            $contents = (string) file_get_contents($path);

            return response()->make($contents, 200, [
                'Content-Type' => 'application/yaml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300',
            ]);
        });
    }
}
