<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\SynchronousDriverInterface;
use Ordinary\Log\TextFormatter;

/**
 * Writes formatted log events to the system logger via syslog().
 *
 * Implements {@see SynchronousDriverInterface} since PHP's syslog() writes to a
 * local socket and completes before returning.
 *
 * When `$ident` is provided, openlog() is called in the constructor to configure
 * the identity, facility, and options. If omitted, PHP defaults to the script name
 * as the ident and LOG_USER as the facility.
 *
 * ```php
 * $logger->add(new SyslogDriver(ident: 'my-app'));
 * ```
 */
final readonly class SyslogDriver implements SynchronousDriverInterface
{
    public function __construct(
        private LogFormatterInterface $formatter = new TextFormatter(),
        string $ident = '',
        int $facility = \LOG_USER,
        int $option = 0,
    ) {
        if ($ident !== '') {
            \openlog($ident, $option, $facility);
        }
    }

    public function handleLog(LogEntryInterface $logItem): void
    {
        \syslog($this->mapLevel($logItem->level), $this->formatter->formatLog($logItem));
    }

    private function mapLevel(LogLevel $level): int
    {
        return match ($level) {
            LogLevel::Debug => \LOG_DEBUG,
            LogLevel::Info => \LOG_INFO,
            LogLevel::Notice => \LOG_NOTICE,
            LogLevel::Warning => \LOG_WARNING,
            LogLevel::Error => \LOG_ERR,
            LogLevel::Critical => \LOG_CRIT,
            LogLevel::Alert => \LOG_ALERT,
            LogLevel::Emergency => \LOG_EMERG,
        };
    }
}
