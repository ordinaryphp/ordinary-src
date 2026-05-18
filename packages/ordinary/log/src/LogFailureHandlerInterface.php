<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogFailureHandlerInterface
{
    /**
     * Handles a log dispatch failure.
     *
     * Implementations must not throw. If they do, the exception will propagate
     * unhandled from the log handler that invoked this.
     */
    public function handleLogFailure(LogFailureExceptionInterface $e): void;
}
