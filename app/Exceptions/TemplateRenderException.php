<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Raised when a template cannot be parsed or evaluated by the safe renderer.
 *
 * The message is always a controlled Persian message (never raw template
 * source or PHP code) and carries the 1-based source line when available.
 */
class TemplateRenderException extends DocumentGenerationException
{
    private readonly ?int $lineNumber;

    private readonly string $rawMessage;

    public function __construct(string $message, ?int $line = null, ?Throwable $previous = null)
    {
        $this->rawMessage = $message;
        $this->lineNumber = $line;

        parent::__construct(
            self::CODE_RENDER_ERROR,
            $line === null ? $message : $message.' (خط '.$line.')',
            $previous,
        );
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    /**
     * Attach a source line to an exception that was raised without one.
     */
    public function withLine(int $line): self
    {
        if ($this->lineNumber !== null) {
            return $this;
        }

        return new self($this->rawMessage, $line);
    }
}
