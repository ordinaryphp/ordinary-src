<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Provides default implementations for the named logging methods by
 * constructing a {@see LogEntry} and delegating to {@see self::log()}.
 *
 * Classes using this trait must implement {@see LoggerInterface::log()}.
 * Assign {@see $clock} in the constructor to supply a custom timestamp source;
 * the default is {@see UtcClock}.
 */
trait LoggerTrait
{
    abstract public function log(LogEntryInterface $logItem): void;

    /**
     * Clock used to timestamp log items. Assign in the constructor to inject a
     * custom source; defaults to {@see UtcClock} on first use.
     */
    protected ClockInterface $clock;

    /** Returns the current timestamp used when constructing log items. */
    protected function now(): DateTimeImmutable
    {
        if (!isset($this->clock)) {
            $this->clock = new UtcClock();
        }

        return $this->clock->now();
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Emergency, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Alert, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Critical, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Error, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Warning, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Notice, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Info, $message, $this->now(), $context));
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(new LogEntry(LogLevel::Debug, $message, $this->now(), $context));
    }
}
