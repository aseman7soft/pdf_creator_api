<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Filesystem\FilesystemException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Translates any throwable into the public API error contract.
 *
 * Internal exception messages, SQL and stack traces are never exposed: only the
 * stable machine code plus a Persian, consumer-safe message are returned. The
 * technical details stay in the application log (written by the middleware).
 */
final class ExceptionMapper
{
    /**
     * Engine error codes produced by the renderer / Gotenberg / storage layers.
     *
     * @var array<string, array{status: int, message: string}>
     */
    private const ENGINE_ERRORS = [
        'GOTENBERG_UNAVAILABLE' => [
            'status' => 503,
            'message' => 'سرویس تولید فایل PDF در حال حاضر در دسترس نیست؛ لطفاً بعداً تلاش کنید.',
        ],
        'GOTENBERG_TIMEOUT' => [
            'status' => 503,
            'message' => 'زمان تولید فایل PDF به پایان رسید؛ لطفاً دوباره تلاش کنید.',
        ],
        'GOTENBERG_ERROR' => [
            'status' => 502,
            'message' => 'سرویس تولید فایل PDF پاسخ معتبری برنگرداند.',
        ],
        'RENDER_ERROR' => [
            'status' => 500,
            'message' => 'پردازش قالب سند با خطای داخلی مواجه شد.',
        ],
        'STORAGE_ERROR' => [
            'status' => 500,
            'message' => 'ذخیره یا بازیابی فایل سند با خطا مواجه شد.',
        ],
        'TEMPLATE_NOT_FOUND' => [
            'status' => 404,
            'message' => 'قالب مورد نظر یافت نشد.',
        ],
        'TEMPLATE_INACTIVE' => [
            'status' => 422,
            'message' => 'قالب مورد نظر غیرفعال است.',
        ],
        'VALIDATION_ERROR' => [
            'status' => 422,
            'message' => 'داده‌های ارسالی نامعتبر است.',
        ],
    ];

    /**
     * @return array{status: int, code: string, message: string, details: array<string, mixed>, headers: array<string, string>}
     */
    public function map(Throwable $e, ?Request $request = null): array
    {
        if ($e instanceof ApiException) {
            return $this->result($e->status, $e->errorCode, $e->getMessage(), $e->details, $e->headers);
        }

        if ($e instanceof ValidationException) {
            return $this->result(
                422,
                'VALIDATION_ERROR',
                'داده‌های ارسالی نامعتبر است.',
                $e->errors(),
            );
        }

        if ($e instanceof InvalidSignatureException) {
            return $this->result(403, 'SIGNATURE_INVALID', 'لینک دانلود نامعتبر یا منقضی شده است.');
        }

        if ($e instanceof AuthenticationException) {
            return $this->result(401, 'UNAUTHENTICATED', 'برای دسترسی به این منبع احراز هویت لازم است.', [], ['WWW-Authenticate' => 'Bearer']);
        }

        if ($e instanceof AuthorizationException) {
            return $this->result(403, 'FORBIDDEN', 'دسترسی به این منبع مجاز نیست.');
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->result(404, 'NOT_FOUND', 'منبع درخواستی یافت نشد.');
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->result(405, 'METHOD_NOT_ALLOWED', 'روش درخواست برای این مسیر مجاز نیست.');
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->result(
                429,
                'RATE_LIMIT_EXCEEDED',
                'تعداد درخواست‌ها بیش از حد مجاز است؛ لطفاً بعداً تلاش کنید.',
                [],
                $e->getHeaders(),
            );
        }

        if ($e instanceof FilesystemException) {
            return $this->result(500, 'STORAGE_ERROR', 'ذخیره یا بازیابی فایل سند با خطا مواجه شد.');
        }

        $basename = class_basename($e);

        // Engine exceptions (owned by the PDF engine module) are mapped by their
        // stable error code accessors; we never depend on their exact namespace.
        if ($this->looksLikeEngineException($basename)) {
            $code = $this->engineErrorCode($e);

            if ($code !== null) {
                $definition = self::ENGINE_ERRORS[$code] ?? [
                    'status' => 500,
                    'message' => 'خطای داخلی در تولید سند رخ داد.',
                ];

                $status = $this->engineStatusCode($e) ?? $definition['status'];

                return $this->result($status, $code, $definition['message']);
            }

            return $this->result(500, 'RENDER_ERROR', 'پردازش سند با خطای داخلی مواجه شد.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            [$code, $message] = match ($status) {
                400 => ['BAD_REQUEST', 'درخواست نامعتبر است.'],
                401 => ['UNAUTHENTICATED', 'برای دسترسی به این منبع احراز هویت لازم است.'],
                403 => ['FORBIDDEN', 'دسترسی به این منبع مجاز نیست.'],
                404 => ['NOT_FOUND', 'منبع درخواستی یافت نشد.'],
                405 => ['METHOD_NOT_ALLOWED', 'روش درخواست برای این مسیر مجاز نیست.'],
                408 => ['REQUEST_TIMEOUT', 'زمان درخواست به پایان رسید.'],
                409 => ['CONFLICT', 'درخواست با وضعیت فعلی منبع در تناقض است.'],
                410 => ['GONE', 'منبع درخواستی دیگر در دسترس نیست.'],
                413 => ['PAYLOAD_TOO_LARGE', 'حجم بدنه درخواست بیش از حد مجاز است.'],
                422 => ['VALIDATION_ERROR', 'داده‌های ارسالی نامعتبر است.'],
                429 => ['RATE_LIMIT_EXCEEDED', 'تعداد درخواست‌ها بیش از حد مجاز است؛ لطفاً بعداً تلاش کنید.'],
                default => ['SERVER_ERROR', 'خطای داخلی سرور رخ داد.'],
            };

            return $this->result($status, $code, $message, [], $e->getHeaders());
        }

        // Anything unexpected: generic message only, details stay in the log.
        return $this->result(500, 'SERVER_ERROR', 'خطای داخلی سرور رخ داد.');
    }

    public function isServerError(array $mapped): bool
    {
        return ($mapped['status'] ?? 500) >= 500;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     * @return array{status: int, code: string, message: string, details: array<string, mixed>, headers: array<string, string>}
     */
    private function result(
        int $status,
        string $code,
        string $message,
        array $details = [],
        array $headers = [],
    ): array {
        return [
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'details' => $details,
            'headers' => $headers,
        ];
    }

    private function looksLikeEngineException(string $basename): bool
    {
        return str_contains($basename, 'Gotenberg')
            || str_contains($basename, 'DocumentGeneration')
            || str_contains($basename, 'TemplateRender')
            || str_contains($basename, 'TemplateSanitizer')
            || str_contains($basename, 'RenderException')
            || str_contains($basename, 'StorageException');
    }

    private function engineErrorCode(Throwable $e): ?string
    {
        foreach (['getErrorCode', 'errorCode'] as $method) {
            if (! method_exists($e, $method)) {
                continue;
            }

            try {
                $code = $e->{$method}();

                if (is_string($code) && $code !== '') {
                    return $code;
                }
            } catch (Throwable) {
                // Fall through to the next accessor.
            }
        }

        if (property_exists($e, 'errorCode')) {
            try {
                $code = $e->errorCode;

                if (is_string($code) && $code !== '') {
                    return $code;
                }
            } catch (Throwable) {
                // Ignore unreadable properties.
            }
        }

        return null;
    }

    private function engineStatusCode(Throwable $e): ?int
    {
        if (! method_exists($e, 'getStatusCode')) {
            return null;
        }

        try {
            $status = $e->getStatusCode();
        } catch (Throwable) {
            return null;
        }

        return is_int($status) && $status >= 400 && $status <= 599 ? $status : null;
    }
}
