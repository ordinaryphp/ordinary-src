<?php

declare(strict_types=1);

namespace Ordinary\Log;

final class LogFailureException extends \RuntimeException implements LogFailureExceptionInterface
{
    /**
     * @param LogItemInterface $logItem
     *                                  The log item that was being dispatched when the failure occurred.
     * @param \Throwable $previous
     *                             The underlying exception thrown by the driver.
     * @param LogDriverInterface|null $failingDriver
     *                                               The driver that threw. Null only when constructed outside the Logger.
     */
    public function __construct(
        private readonly LogItemInterface $logItem,
        \Throwable $previous,
        private readonly ?LogDriverInterface $failingDriver = null,
    ) {
        parent::__construct(
            \sprintf(
                'Log dispatch failed for [%s] "%s": %s',
                $logItem->level->getFullName(),
                $logItem->message,
                $previous->getMessage(),
            ),
            $previous->getCode(),
            $previous,
        );
    }

    public function getLogItem(): LogItemInterface
    {
        return $this->logItem;
    }

    public function getFailingDriver(): ?LogDriverInterface
    {
        return $this->failingDriver;
    }
}
