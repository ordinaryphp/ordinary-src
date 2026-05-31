<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Produces a JSON-encoded log entry with structured fields.
 *
 * When a channel is set on the {@see \Ordinary\Log\Logger}, it appears as the
 * first top-level `"channel"` field. The {@see LogEntryInterface::RESERVED_EXCEPTION}
 * context key, when present and holding a Throwable, is extracted and placed as a
 * top-level `"exception"` field. The original message template is preserved without
 * interpolation so log aggregators receive both the template and the full context.
 */
final readonly class JsonFormatter implements LogFormatterInterface
{
    public function __construct(
        private DateTimeFormatterInterface $dateTimeFormatter = new DateTimeFormatter(),
        private LevelFormatterInterface $levelFormatter = new LevelFormatter(),
        private ?ExceptionFormatterInterface $exceptionFormatter = null,
        private int $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
    ) {}

    public function formatLog(LogEntryInterface $logItem): string
    {
        $context = $logItem->context;

        $channelField = $context[LogEntryInterface::RESERVED_CHANNEL] ?? null;
        unset($context[LogEntryInterface::RESERVED_CHANNEL]);
        $exceptionField = $this->extractException($context);

        $data = [];

        if ($channelField !== null) {
            $data['channel'] = $channelField;
        }

        $data['level'] = $this->levelFormatter->formatLevel($logItem->level);
        $data['date'] = $this->dateTimeFormatter->formatDate($logItem->dateTime);
        $data['message'] = $logItem->message;
        $data['context'] = $this->normalizeContext($context);

        if ($exceptionField !== null) {
            $data['exception'] = $exceptionField;
        }

        return \json_encode($data, $this->jsonFlags | \JSON_THROW_ON_ERROR);
    }

    /**
     * Extracts and formats a Throwable under the exception key from context.
     *
     * Only Throwable values are extracted; non-Throwable values under the same key
     * remain in context and appear in the `context` object as regular fields.
     *
     * @param array<string, mixed> $context
     */
    private function extractException(array &$context): ?string
    {
        if (!\array_key_exists(LogEntryInterface::RESERVED_EXCEPTION, $context)) {
            return null;
        }

        $value = $context[LogEntryInterface::RESERVED_EXCEPTION];

        if (!($value instanceof \Throwable)) {
            return null;
        }

        unset($context[LogEntryInterface::RESERVED_EXCEPTION]);

        return $this->exceptionFormatter instanceof ExceptionFormatterInterface
            ? $this->exceptionFormatter->formatException($value)
            : \sprintf('%s: %s', $value::class, $value->getMessage());
    }

    /**
     * @param array<string, mixed> $context
     *
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
            return $this->exceptionFormatter instanceof ExceptionFormatterInterface
                ? $this->exceptionFormatter->formatException($value)
                : \sprintf('%s: %s', $value::class, $value->getMessage());
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
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
