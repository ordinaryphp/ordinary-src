<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Wraps a native PHP error as a throwable so it can flow through the same
 * handler and matcher pipeline as exceptions.
 *
 * Constructed via {@see self::fromPhpError()} using the arguments passed to a
 * `set_error_handler` callback rather than directly through the constructor.
 */
class ErrorException extends \ErrorException
{
    /**
     * Creates an instance from the arguments PHP passes to a `set_error_handler` callback.
     */
    public static function fromPhpError(
        int $severity,
        string $message,
        string $file = '',
        int $line = 0,
    ): self {
        return new self(
            message: $message,
            code: 0,
            severity: $severity,
            filename: $file,
            line: $line,
        );
    }
}
