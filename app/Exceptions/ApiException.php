<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Api\ApiResponseService;
use App\Services\Api\ExceptionMapper;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * A controlled API failure with a stable machine readable code.
 *
 * The exception renders itself through the exception handler and is also
 * translated by {@see ExceptionMapper}, so controllers may
 * throw it from anywhere in the request lifecycle. It is intentionally not
 * reported by the framework: the API layer logs server errors itself.
 */
class ApiException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $details = [],
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function make(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
        array $headers = [],
    ): self {
        return new self($code, $message, $status, $details, $headers);
    }

    public static function invalidApiKey(string $message = 'کلید API نامعتبر، منقضی یا لغو شده است.'): self
    {
        return new self('INVALID_API_KEY', $message, 401, [], ['WWW-Authenticate' => 'Bearer']);
    }

    public static function unauthenticated(string $message = 'برای دسترسی به این منبع احراز هویت لازم است.'): self
    {
        return new self('UNAUTHENTICATED', $message, 401, [], ['WWW-Authenticate' => 'Bearer']);
    }

    public static function notFound(string $message, string $code = 'NOT_FOUND'): self
    {
        return new self($code, $message, 404);
    }

    public static function forbidden(string $message, string $code = 'FORBIDDEN'): self
    {
        return new self($code, $message, 403);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function unprocessable(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 422, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 409, $details);
    }

    public static function gone(string $code, string $message): self
    {
        return new self($code, $message, 410);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function tooManyRequests(string $code, string $message, array $headers = []): self
    {
        return new self($code, $message, 429, [], $headers);
    }

    /**
     * Render the exception as the canonical API error envelope.
     */
    public function render(Request $request): JsonResponse
    {
        // The request logging middleware reads these attributes after the
        // response is rendered (including 4xx/5xx failures).
        $request->attributes->set('api_error_code', $this->errorCode);
        $request->attributes->set('api_error_message', $this->getMessage());

        return ApiResponseService::error(
            $this->errorCode,
            $this->getMessage(),
            $this->details,
            $this->status,
            $this->headers,
        );
    }
}
