<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects request bodies larger than the configured payload limit (413).
 *
 * The check uses the declared Content-Length first and falls back to the actual
 * raw body length, so chunked requests are protected as well. Bodies are never
 * written to logs.
 */
class EnsureApiPayloadLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $maxKb = max(1, (int) (config('pdf.max_payload_kb') ?? config('api.max_payload_kb', 1024)));
        $maxBytes = $maxKb * 1024;

        $contentLength = $request->headers->get('Content-Length');

        if (is_string($contentLength) && is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            return $this->reject($request, $maxKb);
        }

        $raw = $request->getContent(false);

        if ($raw !== '' && strlen($raw) > $maxBytes) {
            return $this->reject($request, $maxKb);
        }

        return $next($request);
    }

    private function reject(Request $request, int $maxKb): Response
    {
        $message = "حجم بدنه درخواست بیش از حد مجاز است (حداکثر {$maxKb} کیلوبایت).";

        $request->attributes->set('api_error_code', 'PAYLOAD_TOO_LARGE');
        $request->attributes->set('api_error_message', $message);

        return ApiResponseService::error('PAYLOAD_TOO_LARGE', $message, [], 413);
    }
}
