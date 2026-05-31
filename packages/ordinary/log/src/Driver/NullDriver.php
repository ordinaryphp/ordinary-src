<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\SynchronousDriverInterface;

/**
 * Silently discards every log item.
 *
 * Use this as a no-op placeholder in tests, during development when no real
 * driver is needed yet, or to explicitly disable a group:
 *
 * ```php
 * $logger->add(new NullDriver());
 * ```
 */
final class NullDriver implements SynchronousDriverInterface
{
    public function handleLog(LogEntryInterface $logItem): void {}
}
