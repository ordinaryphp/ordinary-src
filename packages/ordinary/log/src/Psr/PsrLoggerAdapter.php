<?php

declare(strict_types=1);

namespace Ordinary\Log\Psr;

use DateTimeImmutable;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LogLevel;
use Ordinary\Log\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Adapts an {@see LoggerInterface} to the PSR-3 {@see PsrLoggerInterface}.
 *
 * Obtain an instance via the concrete logger rather than depending on this
 * adapter directly; it exists solely for interoperability with PSR-3 consumers.
 */
final class PsrLoggerAdapter implements PsrLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /** @param array<string, mixed> $context */
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info((string) $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug((string) $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws \InvalidArgumentException for unrecognised PSR log level strings
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        if (!\is_string($level)) {
            throw new \InvalidArgumentException(
                \sprintf('Log level must be a string, got %s', \get_debug_type($level)),
            );
        }

        $logLevel = $this->mapPsrLevel($level);
        $this->logger->log(new GenericLogItem($logLevel, (string) $message, new DateTimeImmutable(), $context));
    }

    private function mapPsrLevel(string $level): LogLevel
    {
        return match ($level) {
            PsrLogLevel::EMERGENCY => LogLevel::Emergency,
            PsrLogLevel::ALERT => LogLevel::Alert,
            PsrLogLevel::CRITICAL => LogLevel::Critical,
            PsrLogLevel::ERROR => LogLevel::Error,
            PsrLogLevel::WARNING => LogLevel::Warning,
            PsrLogLevel::NOTICE => LogLevel::Notice,
            PsrLogLevel::INFO => LogLevel::Info,
            PsrLogLevel::DEBUG => LogLevel::Debug,
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown PSR log level: "%s"', $level),
            ),
        };
    }
}
