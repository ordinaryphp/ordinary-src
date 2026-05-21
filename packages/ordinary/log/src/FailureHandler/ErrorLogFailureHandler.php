<?php

declare(strict_types=1);

namespace Ordinary\Log\FailureHandler;

use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;

/**
 * Writes log driver failures to PHP's error log via error_log().
 *
 * This is the default failure handler used by {@see \Ordinary\Log\Logger}.
 * It records the failing driver class, log level, message, and the underlying
 * exception message so failures are always visible in the application error log.
 */
final class ErrorLogFailureHandler implements LogFailureHandlerInterface
{
    public function handleLogFailure(LogFailureExceptionInterface $e): void
    {
        $driver = $e->failingDriver;

        \error_log(\sprintf(
            'Log driver failure%s for [%s] "%s": %s',
            $driver instanceof LogDriverInterface ? ' (' . $driver::class . ')' : '',
            $e->logItem->level->getFullName(),
            $e->logItem->message,
            $e->getPrevious()?->getMessage() ?? $e->getMessage(),
        ));
    }
}
