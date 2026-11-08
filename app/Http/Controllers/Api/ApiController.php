<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Services\Api\ExceptionMapper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared helpers for the versioned public API controllers.
 */
abstract class ApiController extends Controller
{
    protected function project(Request $request): ApiProject
    {
        $project = $request->attributes->get('api_project');

        if (! $project instanceof ApiProject) {
            throw ApiException::unauthenticated();
        }

        return $project;
    }

    protected function apiKey(Request $request): ?ApiKey
    {
        $key = $request->attributes->get('api_key');

        return $key instanceof ApiKey ? $key : null;
    }

    /**
     * Store a request attribute on the request instance used by the controller
     * and on the canonical request bound in the container.
     *
     * FormRequest instances are copies of the canonical request created by the
     * container (Symfony attributes are copied by value), while request logging
     * runs on the canonical instance during terminate(). Writing both keeps the
     * log context (template/document ids, error code) accurate.
     */
    protected function attribute(Request $request, string $key, mixed $value): void
    {
        $request->attributes->set($key, $value);

        $canonical = request();

        if ($canonical instanceof Request && $canonical !== $request) {
            $canonical->attributes->set($key, $value);
        }
    }

    /**
     * Convert any unexpected throwable into the canonical ApiException.
     *
     * Laravel renders exceptions at the innermost routing pipeline slice, so
     * the API layer translates them itself before the framework handler sees
     * them. Internal messages are replaced with consumer-safe Persian messages.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function handleApiErrors(Request $request, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $mapper = app(ExceptionMapper::class);
            $mapped = $mapper->map($exception, $request);

            $this->attribute($request, 'api_error_code', $mapped['code']);
            $this->attribute($request, 'api_error_message', $mapped['message']);

            if ($mapped['status'] >= 500) {
                Log::error('API request failed.', [
                    'request_id' => $request->attributes->get('request_id'),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'error_code' => $mapped['code'],
                    'exception' => $exception::class,
                    'message' => Str::limit($exception->getMessage(), 300),
                ]);
            }

            throw new ApiException(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                $mapped['details'],
                $mapped['headers'],
                $exception,
            );
        }
    }
}
