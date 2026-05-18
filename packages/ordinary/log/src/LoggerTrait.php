<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeImmutable;

/**
 * Provides default implementations for the named logging methods by
 * constructing a {@see GenericLogItem} and delegating to {@see self::log()}.
 *
 * Classes using this trait must implement {@see LoggerInterface::log()}.
 */
trait LoggerTrait
{
    abstract public function log(LogItemInterface $logItem): void;

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Emergency, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Alert, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Critical, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Error, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Warning, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Notice, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Info, $message, new DateTimeImmutable(), $context));
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(new GenericLogItem(LogLevel::Debug, $message, new DateTimeImmutable(), $context));
    }
}
