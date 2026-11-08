<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when a template contains constructs that are forbidden outright
 * (raw PHP tags, Blade directives that could reach the application runtime,
 * unescaped output blocks, ...). It is a TemplateRenderException so callers
 * only have to catch the render-error family.
 */
class TemplateSanitizerException extends TemplateRenderException
{
    public function __construct(string $message, ?int $line = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $line, $previous);
    }
}
