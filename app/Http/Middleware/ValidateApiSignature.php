<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the temporary signature of download links.
 *
 * This replaces the framework "signed" middleware so an invalid or expired
 * signature produces the canonical API error envelope instead of a generic
 * exception page. No Bearer token is required on this route.
 */
class ValidateApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasValidSignature()) {
            $message = 'لینک دانلود نامعتبر یا منقضی شده است.';

            $request->attributes->set('api_error_code', 'SIGNATURE_INVALID');
            $request->attributes->set('api_error_message', $message);

            return ApiResponseService::error('SIGNATURE_INVALID', $message, [], 403);
        }

        return $next($request);
    }
}
