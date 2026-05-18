<?php

declare(strict_types=1);

namespace Ordinary\Log\FailureHandler;

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
        $driver = $e->getFailingDriver();

        \error_log(\sprintf(
            'Log driver failure%s for [%s] "%s": %s',
            $driver !== null ? ' (' . $driver::class . ')' : '',
            $e->getLogItem()->level->getFullName(),
            $e->getLogItem()->message,
            $e->getPrevious()?->getMessage() ?? $e->getMessage(),
        ));
    }
}
