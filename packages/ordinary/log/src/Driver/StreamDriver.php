<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogItemInterface;

/**
 * Writes a formatted log line to any writable stream resource.
 */
final class StreamDriver implements LogDriverInterface
{
    /** @var resource */
    private $stream;

    /** @param resource $stream A writable stream resource obtained via fopen(). */
    public function __construct(
        $stream,
        private readonly LogFormatterInterface $formatter,
    ) {
        $this->stream = $stream;
    }

    public function handleLog(LogItemInterface $logItem): void
    {
        \fwrite($this->stream, $this->formatter->formatLog($logItem) . \PHP_EOL);
    }
}
