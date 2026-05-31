<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogProcessorInterface
{
    /**
     * Transforms a log item before it is dispatched to drivers.
     *
     * Must return a {@see LogEntryInterface}: either the original item unchanged or a
     * replacement. Use {@see \Ordinary\Log\CallableProcessor} with wither methods
     * on {@see \Ordinary\Log\ImmutableLogEntryInterface} for inline transformations.
     * Processors must not throw; any exception will propagate unhandled from the Logger.
     */
    public function process(LogEntryInterface $logItem): LogEntryInterface;
}
