<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Called when a {@see ThrowableHandlerInterface} itself throws during dispatch.
 *
 * Implementations must not throw; doing so would mask the original failure.
 */
interface HandlerFailureHandlerInterface
{
    public function handleFailure(
        ThrowableHandlerInterface $handler,
        \Throwable $failure,
        \Throwable $original,
    ): void;
}
