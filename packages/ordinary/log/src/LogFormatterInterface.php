<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogFormatterInterface
{
    /**
     * Produces a fully-interpolated string representation of the log item.
     *
     * Implementations are responsible for resolving {@see LogEntryInterface::RESERVED_DATE}
     * and {@see LogEntryInterface::RESERVED_LEVEL} proxy values and for
     * substituting all {key} placeholders found in the message.
     */
    public function formatLog(LogEntryInterface $logItem): string;
}
