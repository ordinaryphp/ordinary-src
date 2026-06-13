<?php

declare(strict_types=1);

namespace Ordinary\Error\FailureHandler;

use Ordinary\Error\HandlerFailureHandlerInterface;
use Ordinary\Error\ThrowableHandlerInterface;

/**
 * Reports handler failures to PHP's error log.
 *
 * This is the default failure handler used by {@see \Ordinary\Error\ErrorHandler}
 * and {@see \Ordinary\Error\ExceptionHandler}.
 */
final class ErrorLogFailureHandler implements HandlerFailureHandlerInterface
{
    public function handleFailure(
        ThrowableHandlerInterface $handler,
        \Throwable $failure,
        \Throwable $original,
    ): void {
        \error_log(\sprintf(
            'Handler %s failed while processing %s (%s: %s in %s:%d)',
            \explode("\0", $handler::class)[0],
            \explode("\0", $original::class)[0],
            \explode("\0", $failure::class)[0],
            $failure->getMessage(),
            $failure->getFile(),
            $failure->getLine(),
        ));
    }
}
