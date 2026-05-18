<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Produces a JSON-encoded log entry with structured fields.
 *
 * The {@see LogItemInterface::RESERVED_EXCEPTION} context key, when present and
 * holding a Throwable, is extracted and placed as a top-level "exception" field
 * using the supplied exception formatter. The original message template is
 * preserved without interpolation so log aggregators receive both the template
 * and the full context structure.
 */
final class JsonLogFormatter implements LogFormatterInterface
{
    public function __construct(
        private readonly DateTimeFormatterInterface $dateTimeFormatter = new GenericDateTimeFormatter(),
        private readonly LevelFormatterInterface $levelFormatter = new GenericLevelFormatter(),
        private readonly ?ExceptionFormatterInterface $exceptionFormatter = null,
        private readonly int $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
    ) {}

    public function formatLog(LogItemInterface $logItem): string
    {
        $context = $logItem->context;

        $exceptionField = $this->extractException($context);

        $data = [
            'level' => $this->levelFormatter->formatLevel($logItem->level),
            'date' => $this->dateTimeFormatter->formatDate($logItem->dateTime),
            'message' => $logItem->message,
            'context' => $this->normalizeContext($context),
        ];

        if ($exceptionField !== null) {
            $data['exception'] = $exceptionField;
        }

        return \json_encode($data, $this->jsonFlags | \JSON_THROW_ON_ERROR);
    }

    /**
     * Extracts and formats the reserved exception key from context (mutates the array).
     *
     * @param array<string, mixed> $context
     */
    private function extractException(array &$context): ?string
    {
        if (!\array_key_exists(LogItemInterface::RESERVED_EXCEPTION, $context)) {
            return null;
        }

        $value = $context[LogItemInterface::RESERVED_EXCEPTION];
        unset($context[LogItemInterface::RESERVED_EXCEPTION]);

        if (!($value instanceof \Throwable)) {
            return null;
        }

        return $this->exceptionFormatter !== null
            ? $this->exceptionFormatter->formatException($value)
            : \sprintf('%s: %s', $value::class, $value->getMessage());
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_float($value)) {
            if (\is_nan($value)) {
                return 'NaN';
            }

            if (\is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            }

            return $value;
        }

        if (\is_string($value)) {
            return $value;
        }

        if ($value instanceof \Throwable) {
            return $this->exceptionFormatter !== null
                ? $this->exceptionFormatter->formatException($value)
                : \sprintf('%s: %s', $value::class, $value->getMessage());
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            $result = [];

            foreach ($value as $k => $v) {
                $result[$k] = $this->normalizeValue($v);
            }

            return $result;
        }

        if (\is_object($value)) {
            return \sprintf('[object %s]', $value::class);
        }

        return \gettype($value);
    }
}
