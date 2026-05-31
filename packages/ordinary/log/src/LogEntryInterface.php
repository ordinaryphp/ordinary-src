<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

interface LogEntryInterface
{
    /**
     * Context key injected by formatters as a placeholder for the formatted log date.
     * Use `{date}` in message templates to interpolate the formatted timestamp.
     */
    public const string RESERVED_DATE = 'date';

    /**
     * Context key injected by formatters as a placeholder for the formatted log level.
     * Use `{level}` in message templates to interpolate the level name.
     */
    public const string RESERVED_LEVEL = 'level';

    /**
     * Context key for a {@see \Throwable} associated with this log entry.
     * Matches the PSR-3 convention. Pass a Throwable under this key and formatters
     * will render it via {@see ExceptionFormatterInterface} when one is configured.
     *
     * ```php
     * $logger->error('Charge failed', ['exception' => $e, 'order_id' => 'ORD-1']);
     * ```
     */
    public const string RESERVED_EXCEPTION = 'exception';

    /**
     * Context key for the formatted exception message string.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     */
    public const string RESERVED_EXCEPTION_MESSAGE = 'exception.message';

    /**
     * Context key for the exception source line number.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     */
    public const string RESERVED_EXCEPTION_LINE = 'exception.line';

    /**
     * Context key for the exception code.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     */
    public const string RESERVED_EXCEPTION_CODE = 'exception.code';

    /**
     * Context key stamped by {@see \Ordinary\Log\Logger} when a channel name is configured.
     * Formatters expose it as a top-level field or `{channel}` placeholder.
     */
    public const string RESERVED_CHANNEL = 'channel';

    public const array RESERVED_KEYS = [
        self::RESERVED_DATE,
        self::RESERVED_LEVEL,
        self::RESERVED_EXCEPTION,
        self::RESERVED_EXCEPTION_MESSAGE,
        self::RESERVED_EXCEPTION_LINE,
        self::RESERVED_EXCEPTION_CODE,
        self::RESERVED_CHANNEL,
    ];

    public LogLevel $level { get; }

    public DateTimeInterface $dateTime { get; }

    public string $message { get; }

    /**
     * Returns the structured context for this log entry.
     *
     * {@see self::RESERVED_DATE}, {@see self::RESERVED_LEVEL},
     * {@see self::RESERVED_EXCEPTION_MESSAGE}, {@see self::RESERVED_EXCEPTION_LINE},
     * {@see self::RESERVED_EXCEPTION_CODE}, and {@see self::RESERVED_CHANNEL} are
     * prohibited from user-supplied context — they are injected by the Logger or
     * formatters at dispatch/write time.
     * {@see self::RESERVED_EXCEPTION} may be set by callers to attach a Throwable.
     *
     * @var array<string, mixed>
     */
    public array $context { get; }

}
