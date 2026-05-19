<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

interface LogItemInterface
{
    /**
     * Reserved context key injected by formatters – proxy for the formatted log date.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_DATE = '@date';

    /**
     * Reserved context key injected by formatters – proxy for the formatted log level.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_LEVEL = '@level';

    /**
     * Conventional context key for a {@see \Throwable} associated with this log entry.
     * Unlike {@see self::RESERVED_DATE} and {@see self::RESERVED_LEVEL}, this key is
     * user-supplied and is not automatically injected. Formatters handle it specially
     * via {@see ExceptionFormatterInterface} when present.
     */
    public const string RESERVED_EXCEPTION = '@exception';

    /**
     * Reserved context key for the formatted exception message string.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_EXCEPTION_MESSAGE = '@exception.message';

    /**
     * Reserved context key for the exception source line number.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_EXCEPTION_LINE = '@exception.line';

    /**
     * Reserved context key for the exception code.
     * Injected by formatters from the attached {@see self::RESERVED_EXCEPTION} Throwable.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_EXCEPTION_CODE = '@exception.code';

    /**
     * Reserved context key stamped by {@see \Ordinary\Log\Logger} when a channel name
     * is configured. Formatters expose it as a top-level field or `{@channel}` placeholder.
     * Must not appear in user-supplied context.
     */
    public const string RESERVED_CHANNEL = '@channel';

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
     * Returns the structured context for this log item.
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
