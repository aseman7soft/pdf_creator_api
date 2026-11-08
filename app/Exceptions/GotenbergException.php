<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Controlled Gotenberg (PDF engine) failure.
 *
 * Contract used by the API layer:
 *  - getErrorCode(): GOTENBERG_UNAVAILABLE | GOTENBERG_TIMEOUT | GOTENBERG_ERROR
 *  - getStatusCode(): 503 (unavailable) | 504 (timeout) | 502 (upstream error)
 */
class GotenbergException extends DocumentGenerationException
{
    public const CODE_UNAVAILABLE = 'GOTENBERG_UNAVAILABLE';

    public const CODE_TIMEOUT = 'GOTENBERG_TIMEOUT';

    public const CODE_ERROR = 'GOTENBERG_ERROR';

    public function __construct(
        string $errorCode,
        private readonly int $statusCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $message, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self(
            self::CODE_UNAVAILABLE,
            503,
            'سرویس تولید PDF (Gotenberg) در دسترس نیست.',
            $previous,
        );
    }

    public static function timeout(?Throwable $previous = null): self
    {
        return new self(
            self::CODE_TIMEOUT,
            504,
            'زمان انتظار برای سرویس تولید PDF به پایان رسید.',
            $previous,
        );
    }

    public static function error(?int $httpStatus = null, ?Throwable $previous = null): self
    {
        $message = 'سرویس تولید PDF خطا بازگرداند.';

        if ($httpStatus !== null && $httpStatus > 0) {
            $message .= ' (کد '.$httpStatus.')';
        }

        return new self(self::CODE_ERROR, 502, $message, $previous);
    }
}
