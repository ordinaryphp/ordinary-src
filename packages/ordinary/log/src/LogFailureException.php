<?php

declare(strict_types=1);

namespace Ordinary\Log;

final class LogFailureException extends \RuntimeException implements LogFailureExceptionInterface
{
    /**
     * @param LogEntryInterface $logItem
     *                                   The log item that was being dispatched when the failure occurred.
     * @param \Throwable $previous
     *                             The underlying exception thrown by the driver.
     * @param LogDriverInterface|null $failingDriver
     *                                               The driver that threw. Null only when constructed outside the Logger.
     */
    public function __construct(
        public readonly LogEntryInterface $logItem,
        \Throwable $previous,
        public readonly ?LogDriverInterface $failingDriver = null,
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
}
