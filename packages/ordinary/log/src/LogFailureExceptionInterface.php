<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogFailureExceptionInterface extends \Throwable
{
    /**
     * The log item that was being dispatched when the failure occurred.
     */
    public function getLogItem(): LogItemInterface;

    /**
     * The driver that threw, when the failure was reported by the Logger.
     *
     * Always non-null when thrown by {@see \Ordinary\Log\Logger}. May be null
     * in custom logger implementations that construct this exception without
     * a driver reference.
     */
    public function getFailingDriver(): ?LogDriverInterface;
}
