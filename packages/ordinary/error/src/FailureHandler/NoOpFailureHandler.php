<?php

declare(strict_types=1);

namespace Ordinary\Error\FailureHandler;

use Ordinary\Error\HandlerFailureHandlerInterface;
use Ordinary\Error\ThrowableHandlerInterface;

/**
 * Silently discards handler failures.
 *
 * Use this when handler failures should never produce output, for example in
 * production environments where a log backend being unavailable must not
 * affect the response.
 */
final class NoOpFailureHandler implements HandlerFailureHandlerInterface
{
    public function handleFailure(
        ThrowableHandlerInterface $handler,
        \Throwable $failure,
        \Throwable $original,
    ): void {}
}
