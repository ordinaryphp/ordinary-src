<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\TextFormatter;

/**
 * Writes a formatted log line to any writable stream resource.
 *
 * Defaults to {@see TextFormatter} for human-readable text output.
 * Pass a {@see \Ordinary\Log\JsonFormatter} for structured JSON lines.
 */
final class StreamDriver implements LogDriverInterface
{
    /** @param resource $stream A writable stream resource obtained via fopen(). */
    public function __construct(private $stream, private readonly LogFormatterInterface $formatter = new TextFormatter()) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        \fwrite($this->stream, $this->formatter->formatLog($logItem) . \PHP_EOL);
    }
}
