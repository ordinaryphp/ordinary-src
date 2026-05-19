<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\GenericLogFormatter;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogItemInterface;

/**
 * Writes a formatted log line to any writable stream resource.
 *
 * Defaults to {@see GenericLogFormatter} for human-readable text output.
 * Pass a {@see \Ordinary\Log\JsonLogFormatter} for structured JSON lines.
 */
final class StreamDriver implements LogDriverInterface
{
    /** @param resource $stream A writable stream resource obtained via fopen(). */
    public function __construct(private $stream, private readonly LogFormatterInterface $formatter = new GenericLogFormatter())
    {
    }

    public function handleLog(LogItemInterface $logItem): void
    {
        \fwrite($this->stream, $this->formatter->formatLog($logItem) . \PHP_EOL);
    }
}
