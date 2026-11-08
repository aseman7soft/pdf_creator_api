<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Services\Api\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-API-key sliding window rate limiting plus the project monthly quota.
 *
 * Successful and failed responses all carry the X-RateLimit-* headers so that
 * consumers can back off gracefully. A client over the limit receives a 429
 * with Retry-After.
 */
class ApiRateLimiter
{
    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->attributes->get('api_key');
        $project = $request->attributes->get('api_project');

        if (! $key instanceof ApiKey || ! $project instanceof ApiProject) {
            return $next($request);
        }

        if ($project->isQuotaExhausted()) {
            return $this->quotaExceeded($request, $project);
        }

        $limit = max(1, $key->effectiveRateLimit());
        $cacheKey = 'api-rate:key:'.$key->getKey();

        if (RateLimiter::tooManyAttempts($cacheKey, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($cacheKey));

            $headers = [
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) now()->addSeconds($retryAfter)->getTimestamp(),
            ];

            $this->markError($request, 'RATE_LIMIT_EXCEEDED', 'تعداد درخواست‌ها بیش از حد مجاز است؛ لطفاً بعداً تلاش کنید.');

            return ApiResponseService::error(
                'RATE_LIMIT_EXCEEDED',
                'تعداد درخواست‌ها بیش از حد مجاز است؛ لطفاً بعداً تلاش کنید.',
                [],
                429,
                $headers,
            );
        }

        $attempts = RateLimiter::hit($cacheKey, self::DECAY_SECONDS);
        $remaining = max(0, $limit - $attempts);
        $reset = now()->addSeconds(self::DECAY_SECONDS)->getTimestamp();

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) $reset);

        return $response;
    }

    private function quotaExceeded(Request $request, ApiProject $project): Response
    {
        $quota = (int) ($project->monthly_quota ?? 0);
        $reset = now()->startOfMonth()->addMonth()->getTimestamp();
        $retryAfter = max(1, $reset - now()->getTimestamp());
        $message = 'سهمیه ماهانه این پروژه به پایان رسیده است.';

        $headers = [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $quota,
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) $reset,
        ];

        $this->markError($request, 'MONTHLY_QUOTA_EXCEEDED', $message);

        return ApiResponseService::error('MONTHLY_QUOTA_EXCEEDED', $message, [], 429, $headers);
    }

    private function markError(Request $request, string $code, string $message): void
    {
        $request->attributes->set('api_error_code', $code);
        $request->attributes->set('api_error_message', $message);
    }
}
