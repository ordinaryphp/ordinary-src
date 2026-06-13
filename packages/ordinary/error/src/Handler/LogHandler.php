<?php

declare(strict_types=1);

namespace Ordinary\Error\Handler;

use Ordinary\Error\ThrowableHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Logs each throwable through a PSR-3 logger.
 *
 * PHP error severity levels ({@see \ErrorException}) are mapped to PSR-3 log
 * levels automatically. Other throwable types use `$defaultLevel`.
 *
 * The throwable is passed as `context['exception']` so PSR-3 formatters can
 * render the stack trace.
 */
final readonly class LogHandler implements ThrowableHandlerInterface
{
    /**
     * @param string $defaultLevel PSR-3 level used for non-`\ErrorException` throwables
     *                             and for error severities that do not map to a specific level.
     *                             Defaults to {@see LogLevel::ERROR}.
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $defaultLevel = LogLevel::ERROR,
    ) {}

    public function handle(\Throwable $throwable): void
    {
        $this->logger->log(
            $this->resolveLevel($throwable),
            $throwable->getMessage(),
            ['exception' => $throwable],
        );
    }

    private function resolveLevel(\Throwable $throwable): string
    {
        if (!($throwable instanceof \ErrorException)) {
            return $this->defaultLevel;
        }

        $severity = $throwable->getSeverity();

        return match (true) {
            ($severity & (E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR)) !== 0 => LogLevel::CRITICAL,
            ($severity & E_RECOVERABLE_ERROR) !== 0 => LogLevel::ERROR,
            ($severity & (E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) !== 0 => LogLevel::WARNING,
            ($severity & (E_NOTICE | E_USER_NOTICE)) !== 0 => LogLevel::NOTICE,
            ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0 => LogLevel::INFO,
            default => $this->defaultLevel,
        };
    }
}
