<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Interpolates {key} placeholders in the message string using the log item's
 * context, with {@see LogItemInterface::RESERVED_DATE} and
 * {@see LogItemInterface::RESERVED_LEVEL} injected automatically from the
 * supplied formatters.
 *
 * When an {@see ExceptionFormatterInterface} is provided, any Throwable value
 * in context — including {@see LogItemInterface::RESERVED_EXCEPTION} — is
 * formatted with full detail; otherwise the fallback is "ClassName: message".
 */
final readonly class GenericLogFormatter implements LogFormatterInterface
{
    public function __construct(
        private DateTimeFormatterInterface $dateTimeFormatter = new GenericDateTimeFormatter(),
        private LevelFormatterInterface $levelFormatter = new GenericLevelFormatter(),
        private ?ExceptionFormatterInterface $exceptionFormatter = null,
    ) {}

    public function formatLog(LogItemInterface $logItem): string
    {
        $context = $logItem->context;
        $context[LogItemInterface::RESERVED_DATE] = $this->dateTimeFormatter->formatDate($logItem->dateTime);
        $context[LogItemInterface::RESERVED_LEVEL] = $this->levelFormatter->formatLevel($logItem->level);

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
        if ($value instanceof \Throwable) {
            return $this->exceptionFormatter instanceof ExceptionFormatterInterface
                ? $this->exceptionFormatter->formatException($value)
                : \sprintf('%s: %s', $value::class, $value->getMessage());
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (\is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return 'null';
        }

        return get_debug_type($value);
    }
}
