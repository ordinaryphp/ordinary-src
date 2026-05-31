<?php

declare(strict_types=1);

namespace Ordinary\Log\Psr;

use Ordinary\Log\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Adapts an {@see LoggerInterface} to the PSR-3 {@see PsrLoggerInterface}.
 *
 * PSR-3 specifies that exceptions passed in context MUST use the key `"exception"`.
 * Because {@see \Ordinary\Log\LogEntryInterface::RESERVED_EXCEPTION} is also `"exception"`,
 * no translation is required — pass context as-is and formatters will handle the
 * Throwable correctly.
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
        $this->logger->emergency((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info((string) $message, $this->castContext($context));
    }

    /** @param array<mixed> $context */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug((string) $message, $this->castContext($context));
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

        $msg = (string) $message;
        $ctx = $this->castContext($context);

        match ($level) {
            PsrLogLevel::EMERGENCY => $this->logger->emergency($msg, $ctx),
            PsrLogLevel::ALERT     => $this->logger->alert($msg, $ctx),
            PsrLogLevel::CRITICAL  => $this->logger->critical($msg, $ctx),
            PsrLogLevel::ERROR     => $this->logger->error($msg, $ctx),
            PsrLogLevel::WARNING   => $this->logger->warning($msg, $ctx),
            PsrLogLevel::NOTICE    => $this->logger->notice($msg, $ctx),
            PsrLogLevel::INFO      => $this->logger->info($msg, $ctx),
            PsrLogLevel::DEBUG     => $this->logger->debug($msg, $ctx),
            default                => throw new \InvalidArgumentException(
                \sprintf('Unknown PSR log level: "%s"', $level),
            ),
        };
    }

    /**
     * Casts a PSR-3 context array to the typed array expected by LoggerInterface.
     *
     * PSR-3 uses `"exception"` for Throwable context, which matches
     * {@see \Ordinary\Log\LogEntryInterface::RESERVED_EXCEPTION} directly — no key
     * translation is needed.
     *
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    private function castContext(array $context): array
    {
        /** @var array<string, mixed> $context */
        return $context;
    }
}
