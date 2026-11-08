<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Api\ApiResponseService;
use App\Services\Gotenberg\GotenbergService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Public, unauthenticated service health probe.
 *
 * The payload is cached for a minute to keep the endpoint cheap when it is
 * polled by load balancers. No URLs, credentials or internal details leak.
 */
class HealthController extends ApiController
{
    private const CACHE_KEY = 'api.health.v1';

    private const CACHE_SECONDS = 60;

    public function show(): JsonResponse
    {
        return $this->handleApiErrors(request(), function (): JsonResponse {
            /** @var array<string, mixed> $payload */
            $payload = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->checks());

            return ApiResponseService::success($payload);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function checks(): array
    {
        $databaseOk = $this->databaseHealthy();
        $gotenbergOk = $this->gotenbergHealthy();

        return [
            'status' => $databaseOk && $gotenbergOk ? 'ok' : 'degraded',
            'service' => 'pdf-service',
            'version' => (string) config('api.version', 'v1'),
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $databaseOk ? 'ok' : 'error',
                'gotenberg' => $gotenbergOk ? 'ok' : 'unavailable',
            ],
        ];
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function gotenbergHealthy(): bool
    {
        if (! class_exists(GotenbergService::class)) {
            return false;
        }

        try {
            $service = app(GotenbergService::class);

            if (! method_exists($service, 'health')) {
                return false;
            }

            /** @var mixed $result */
            $result = $service->health();

            if (is_bool($result)) {
                return $result;
            }

            if (! is_array($result)) {
                return false;
            }

            if (array_key_exists('ok', $result)) {
                return (bool) $result['ok'];
            }

            if (array_key_exists('healthy', $result)) {
                return (bool) $result['healthy'];
            }

            if (array_key_exists('status', $result)) {
                return in_array(strtolower((string) $result['status']), ['ok', 'up', 'healthy'], true);
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
