<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Services\Api\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the "Authorization: Bearer <key>" header against the API key
 * store. Revoked, expired, unknown keys and inactive projects are rejected with
 * a uniform 401 response.
 */
class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->bearerToken($request);

        if ($plain === null) {
            return $this->reject(
                $request,
                'هدر Authorization با طرح Bearer الزامی است.',
            );
        }

        $key = $this->keys->authenticate($plain);

        if (! $key instanceof ApiKey || ! $key->project instanceof ApiProject) {
            return $this->reject(
                $request,
                'کلید API نامعتبر، منقضی یا لغو شده است.',
            );
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_project', $key->project);

        $key->touchUsage();
        $key->project->touchActivity();

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $header, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }

    private function reject(Request $request, string $message): Response
    {
        $exception = ApiException::invalidApiKey($message);

        $request->attributes->set('api_error_code', $exception->errorCode);
        $request->attributes->set('api_error_message', $message);

        return $exception->render($request);
    }
}
