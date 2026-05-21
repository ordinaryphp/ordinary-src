<?php

declare(strict_types=1);

namespace Ordinary\Log\Psr;

use DateTimeImmutable;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LoggerInterface;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Adapts an {@see LoggerInterface} to the PSR-3 {@see PsrLoggerInterface}.
 *
 * PSR-3 specifies that an exception passed in context MUST use the key
 * {@see https://www.php-fig.org/psr/psr-3/#13-context "exception"}. This adapter
 * automatically translates that key to {@see LogItemInterface::RESERVED_EXCEPTION}
 * so formatters handle it correctly. Non-Throwable values under "exception" are
 * left as-is.
 *
 * Obtain an instance via the concrete logger rather than depending on this
 * adapter directly; it exists solely for interoperability with PSR-3 consumers.
 */
final readonly class PsrLoggerAdapter implements PsrLoggerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /** @param array<mixed> $context */
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info((string) $message, $this->translateContext($context));
    }

    /** @param array<mixed> $context */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug((string) $message, $this->translateContext($context));
    }

    /**
     * @param array<mixed> $context
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
        $this->logger->log(new GenericLogItem($logLevel, (string) $message, new DateTimeImmutable(), $this->translateContext($context)));
    }

    /**
     * Translates the PSR-3 "exception" context key to {@see LogItemInterface::RESERVED_EXCEPTION}.
     *
     * Only Throwable values are translated; non-Throwable values under "exception" are
     * passed through unchanged so they appear as regular context in the log output.
     *
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    private function translateContext(array $context): array
    {
        /** @var array<string, mixed> $context */
        if (\array_key_exists('exception', $context) && $context['exception'] instanceof \Throwable) {
            $context[LogItemInterface::RESERVED_EXCEPTION] = $context['exception'];
            unset($context['exception']);
        }

        return $context;
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
