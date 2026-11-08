<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every controlled document-generation failure.
 *
 * The error code is part of the public contract (see docs/CONTRACTS.md §4):
 * GOTENBERG_UNAVAILABLE, GOTENBERG_TIMEOUT, GOTENBERG_ERROR, RENDER_ERROR,
 * STORAGE_ERROR, VALIDATION_ERROR, TEMPLATE_NOT_FOUND, TEMPLATE_INACTIVE.
 *
 * Messages are user-facing Persian strings and must never contain raw PHP,
 * template source, secrets, API keys or payload data.
 */
class DocumentGenerationException extends RuntimeException
{
    public const CODE_GOTENBERG_UNAVAILABLE = 'GOTENBERG_UNAVAILABLE';

    public const CODE_GOTENBERG_TIMEOUT = 'GOTENBERG_TIMEOUT';

    public const CODE_GOTENBERG_ERROR = 'GOTENBERG_ERROR';

    public const CODE_RENDER_ERROR = 'RENDER_ERROR';

    public const CODE_STORAGE_ERROR = 'STORAGE_ERROR';

    public const CODE_VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const CODE_TEMPLATE_NOT_FOUND = 'TEMPLATE_NOT_FOUND';

    public const CODE_TEMPLATE_INACTIVE = 'TEMPLATE_INACTIVE';

    public function __construct(
        private readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function renderError(string $message, ?Throwable $previous = null): static
    {
        return new static(self::CODE_RENDER_ERROR, $message, $previous);
    }

    public static function storageError(string $message, ?Throwable $previous = null): static
    {
        return new static(self::CODE_STORAGE_ERROR, $message, $previous);
    }

    public static function templateNotFound(string $message = 'قالب درخواستی یافت نشد.'): static
    {
        return new static(self::CODE_TEMPLATE_NOT_FOUND, $message);
    }

    public static function templateInactive(string $message = 'قالب انتخاب‌شده غیرفعال است و قابل استفاده نیست.'): static
    {
        return new static(self::CODE_TEMPLATE_INACTIVE, $message);
    }

    public static function validationError(string $message = 'داده‌های ارسالی با متغیرهای قالب سازگار نیستند.'): static
    {
        return new static(self::CODE_VALIDATION_ERROR, $message);
    }
}
