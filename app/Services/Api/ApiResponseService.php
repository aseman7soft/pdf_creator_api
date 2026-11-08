<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the uniform API envelopes.
 *
 * Success: {"success": true, "data": ..., "meta": {...}, "request_id": "..."}
 * Error:   {"success": false, "error": {code, message, details}, "request_id": "..."}
 */
final class ApiResponseService
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, string>  $headers
     */
    public static function success(
        mixed $data = null,
        ?array $meta = null,
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        $body = [
            'success' => true,
            'data' => $data ?? (object) [],
            'meta' => $meta ?? (object) [],
        ];

        if (($requestId = self::requestId()) !== null) {
            $body['request_id'] = $requestId;
        }

        return self::json($body, $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function error(
        string $code,
        string $message,
        array $details = [],
        int $status = 400,
        array $headers = [],
    ): JsonResponse {
        $body = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details === [] ? (object) [] : $details,
            ],
        ];

        if (($requestId = self::requestId()) !== null) {
            $body['request_id'] = $requestId;
            $headers['X-Request-Id'] = $requestId;
        }

        return self::json($body, $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private static function json(array $body, int $status, array $headers): JsonResponse
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        return new JsonResponse(
            $body,
            $status,
            $headers,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public static function requestId(?Request $request = null): ?string
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return null;
        }

        $requestId = $request->attributes->get('request_id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }
}
