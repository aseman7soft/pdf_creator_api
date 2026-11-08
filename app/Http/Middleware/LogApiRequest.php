<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Assigns a request id, emits X-Request-Id and persists one api_request_logs
 * row for every API request — including 401/403/429/500 responses.
 *
 * Persistence happens in terminate() so the final rendered status code is
 * available. Request bodies, Authorization headers and secrets are never
 * written; error messages are sanitized and truncated.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('api_request_started_at', microtime(true));

        $response = $next($request);

        if (! $response->headers->has('X-Request-Id')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->persist($request, $response);
        } catch (Throwable $e) {
            // Logging must never break an API response.
            Log::warning('Failed to persist API request log.', [
                'error' => $e::class,
                'message' => Str::limit($e->getMessage(), 200),
            ]);
        }
    }

    private function persist(Request $request, Response $response): void
    {
        $requestId = $request->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            return;
        }

        $startedAt = $request->attributes->get('api_request_started_at');
        $durationMs = is_float($startedAt) || is_int($startedAt)
            ? (int) round((microtime(true) - (float) $startedAt) * 1000)
            : 0;

        $key = $request->attributes->get('api_key');
        $project = $request->attributes->get('api_project');
        $templateId = $request->attributes->get('api_template_id');
        $documentId = $request->attributes->get('api_generated_document_id');
        $errorCode = $request->attributes->get('api_error_code');
        $errorMessage = $request->attributes->get('api_error_message');

        ApiRequestLog::query()->create([
            'request_id' => $requestId,
            'api_project_id' => $project instanceof ApiProject ? $project->getKey() : null,
            'api_key_id' => $key instanceof ApiKey ? $key->getKey() : null,
            'template_id' => is_int($templateId) ? $templateId : null,
            'generated_document_id' => is_int($documentId) ? $documentId : null,
            'method' => Str::limit($request->method(), 10, ''),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
            'route_name' => $this->routeName($request),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => max(0, $durationMs),
            'request_bytes' => $this->requestBytes($request),
            'response_bytes' => $this->responseBytes($response),
            'ip_address' => $this->ipAddress($request),
            'user_agent' => $this->sanitize(Str::limit((string) $request->userAgent(), 255, '')),
            'error_code' => is_string($errorCode) ? Str::limit($errorCode, 60, '') : null,
            'error_message' => is_string($errorMessage) ? $this->sanitize(Str::limit($errorMessage, 500, '')) : null,
            'created_at' => now(),
        ]);
    }

    private function routeName(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? Str::limit($name, 100, '') : null;
    }

    private function requestBytes(Request $request): int
    {
        $contentLength = $request->headers->get('Content-Length');

        if (is_string($contentLength) && is_numeric($contentLength)) {
            return max(0, (int) $contentLength);
        }

        return strlen((string) $request->getContent(false));
    }

    private function responseBytes(Response $response): ?int
    {
        $contentLength = $response->headers->get('Content-Length');

        if (is_string($contentLength) && is_numeric($contentLength)) {
            return max(0, (int) $contentLength);
        }

        if ($response instanceof StreamedResponse) {
            return null;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }

    private function ipAddress(Request $request): ?string
    {
        $ip = $request->ip();

        return is_string($ip) && $ip !== '' ? Str::limit($ip, 45, '') : null;
    }

    /**
     * Collapse newlines, remove absolute file paths and trim the message so no
     * stack trace or filesystem detail leaks into the request log.
     */
    private function sanitize(string $message): string
    {
        $message = (string) preg_replace('/\s+/u', ' ', $message);
        $message = (string) preg_replace('/[A-Za-z]:\\\\[^\s]+/', '[path]', $message);
        $message = (string) preg_replace('#/(?:[^\s/]+/)+[^\s/]+\.php#', '[path]', $message);

        return trim(Str::limit($message, 500, ''));
    }
}
