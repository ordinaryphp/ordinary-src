<?php

declare(strict_types=1);

namespace Ordinary\Log\Processor;

use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\Logger;
use Ordinary\Log\LogProcessorInterface;

/**
 * Adds the file, line, class, and function of the actual log call site.
 *
 * Walks the call stack and skips frames belonging to the logging infrastructure
 * (anything in the `Ordinary\Log\` namespace by default, plus any additional
 * namespaces supplied via `$skipNamespaces`). The first non-skipped frame's
 * caller location is recorded:
 *
 * ```php
 * $logger->addProcessor(new IntrospectionProcessor());
 * // Adds: log.file, log.line, log.class, log.function
 * ```
 *
 * If your application wraps ordinary/log in its own logger class, pass that
 * namespace in `$skipNamespaces` so the walk continues past the wrapper:
 *
 * ```php
 * new IntrospectionProcessor(skipNamespaces: ['App\\Logging\\'])
 * ```
 *
 * Added context keys (only present when determinable):
 * - `log.file`     — absolute path of the file containing the log call
 * - `log.line`     — line number of the log call
 * - `log.class`    — fully-qualified class name of the caller (if applicable)
 * - `log.function` — function or method name of the caller
 */
final readonly class IntrospectionProcessor implements LogProcessorInterface
{
    /** @var list<string> */
    private array $skipNamespaces;

    /**
     * @param list<string> $skipNamespaces Additional namespace prefixes to skip.
     *                                     Always skips `Ordinary\Log\Processor\` and
     *                                     `Ordinary\Log\Logger` (the processor and Logger
     *                                     dispatch chain). Add your own wrapper namespaces
     *                                     when your application wraps the logger.
     */
    public function __construct(array $skipNamespaces = [])
    {
        $this->skipNamespaces = \array_merge(
            ['Ordinary\\Log\\Processor\\', Logger::class],
            $skipNamespaces,
        );
    }

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        $frame = $this->findCallSite(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));

        if ($frame === null) {
            return $logItem;
        }

        /** @var array<string, int|string> $context */
        $context = \array_filter([
            'log.file'     => $frame['file'] ?? null,
            'log.line'     => $frame['line'] ?? null,
            'log.class'    => $frame['class'] ?? null,
            'log.function' => $frame['function'] ?? null,
        ], fn(mixed $v): bool => $v !== null);

        if ($context === []) {
            return $logItem;
        }

        if ($logItem instanceof ImmutableLogEntryInterface) {
            return $logItem->withContext($context);
        }

        return new LogEntry(
            $logItem->level,
            $logItem->message,
            $logItem->dateTime,
            \array_merge($logItem->context, $context),
        );
    }

    /**
     * Walks the backtrace to find the call site of the log statement.
     *
     * Each frame's `file`/`line` fields point to WHERE that frame's function was
     * called from, not where the function is defined. The last log-infrastructure
     * frame's `file`/`line` is therefore the user call site. When the first
     * non-skipped frame is found, the previous (last skipped) frame supplies the
     * location and the current frame supplies the class/function name.
     *
     * @param list<array<string, mixed>> $trace
     *
     * @return array<string, mixed>|null
     */
    private function findCallSite(array $trace): ?array
    {
        $prevFrame = null;

        foreach ($trace as $frame) {
            $rawClass = $frame['class'] ?? null;
            $class = \is_string($rawClass) ? $rawClass : '';
            $isLogFrame = $this->isSkipped($class);

            if (!$isLogFrame && $prevFrame !== null) {
                return [
                    'file'     => $prevFrame['file'] ?? null,
                    'line'     => $prevFrame['line'] ?? null,
                    'class'    => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            }

            $prevFrame = $frame;
        }

        return null;
    }

    private function isSkipped(string $class): bool
    {
        return \array_any($this->skipNamespaces, fn(string $prefix): bool => \str_starts_with($class, $prefix));
    }
}
