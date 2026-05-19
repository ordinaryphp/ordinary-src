<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogProcessorInterface
{
    /**
     * Transforms a log item before it is dispatched to drivers.
     *
     * Must return a {@see LogItemInterface}: either the original item unchanged or a
     * replacement. Use {@see \Ordinary\Log\GenericCallableProcessor} with wither methods
     * on {@see \Ordinary\Log\ImmutableLogItemInterface} for inline transformations.
     * Processors must not throw; any exception will propagate unhandled from the Logger.
     */
    public function process(LogItemInterface $logItem): LogItemInterface;
}
