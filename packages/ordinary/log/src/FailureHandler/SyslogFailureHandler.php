<?php

declare(strict_types=1);

namespace Ordinary\Log\FailureHandler;

use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;

/**
 * Writes log dispatch failures to syslog.
 *
 * Calls PHP's syslog() directly. If openlog() has not been called by the
 * application, PHP uses the script name as the ident and LOG_USER as the
 * facility. Call openlog() before this handler is first invoked if you need
 * custom ident or facility settings.
 */
final class SyslogFailureHandler implements LogFailureHandlerInterface
{
    public function handleLogFailure(LogFailureExceptionInterface $e): void
    {
        \syslog(\LOG_ERR, $e->getMessage());
    }
}
