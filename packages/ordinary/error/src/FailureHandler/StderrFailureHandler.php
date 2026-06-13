<?php

declare(strict_types=1);

namespace Ordinary\Error\FailureHandler;

use Ordinary\Error\HandlerFailureHandlerInterface;
use Ordinary\Error\ThrowableHandlerInterface;

/**
 * Reports handler failures by writing a formatted message to STDERR.
 */
final class StderrFailureHandler implements HandlerFailureHandlerInterface
{
    public function handleFailure(
        ThrowableHandlerInterface $handler,
        \Throwable $failure,
        \Throwable $original,
    ): void {
        \fwrite(\STDERR, \sprintf(
            'Handler %s failed while processing %s (%s: %s in %s:%d)' . \PHP_EOL,
            \explode("\0", $handler::class)[0],
            \explode("\0", $original::class)[0],
            \explode("\0", $failure::class)[0],
            $failure->getMessage(),
            $failure->getFile(),
            $failure->getLine(),
        ));
    }
}
