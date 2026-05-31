<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Produces a human-readable log line by interpolating `{key}` placeholders in
 * the message string using the log item's context.
 *
 * {@see LogEntryInterface::RESERVED_DATE} and {@see LogEntryInterface::RESERVED_LEVEL}
 * are injected automatically, so `{date}` and `{level}` are always available.
 *
 * Default output format:
 * ```
 * [{date}] [{level}] {message} {remaining context key=value pairs...}
 * ```
 *
 * Example — with the default formatters and a `channel` stamped on the item:
 * ```
 * [2024-06-01T12:00:00Z] [error] Something failed order_id=ORD-999
 * ```
 *
 * When an {@see ExceptionFormatterInterface} is provided, any Throwable value
 * in context — including {@see LogEntryInterface::RESERVED_EXCEPTION} — is
 * formatted with full detail; otherwise the fallback is "ClassName: message".
 */
final readonly class TextFormatter implements LogFormatterInterface
{
    public function __construct(
        private DateTimeFormatterInterface $dateTimeFormatter = new DateTimeFormatter(),
        private LevelFormatterInterface $levelFormatter = new LevelFormatter(),
        private ?ExceptionFormatterInterface $exceptionFormatter = null,
    ) {}

    public function formatLog(LogEntryInterface $logItem): string
    {
        $context = $logItem->context;
        $context[LogEntryInterface::RESERVED_DATE] = $this->dateTimeFormatter->formatDate($logItem->dateTime);
        $context[LogEntryInterface::RESERVED_LEVEL] = $this->levelFormatter->formatLevel($logItem->level);

        return $this->interpolate($logItem->message, $context);
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        /** @var array<string, string> $replacements */
        $replacements = [];

        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = $this->stringify($value);
        }

        return \strtr($message, $replacements);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof \Throwable => $this->exceptionFormatter instanceof ExceptionFormatterInterface
                ? $this->exceptionFormatter->formatException($value)
                : \sprintf('%s: %s', $value::class, $value->getMessage()),
            $value instanceof \Stringable => (string) $value,
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_float($value) => (string) $value,
            \is_string($value) => $value,
            $value === null => 'null',
            default => \get_debug_type($value),
        };
    }
}
