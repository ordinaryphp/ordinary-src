<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\SynchronousDriverInterface;
use Ordinary\Log\TextFormatter;

/**
 * Writes formatted log lines to a date-rotated file.
 *
 * The file path is derived by substituting `{date}` in `$pathPattern` with the
 * formatted date of the log item being written. A new file handle is opened
 * whenever the date changes, enabling daily (or other period) rotation with no
 * external tooling:
 *
 * ```php
 * $logger->add(new RotatingStreamDriver('/var/log/app-{date}.log'));
 * // Produces: /var/log/app-2024-06-01.log, /var/log/app-2024-06-02.log, …
 * ```
 *
 * The `$dateFormat` parameter controls the granularity of rotation. Use
 * `'Y-m-d'` (default) for daily files, `'Y-m-d-H'` for hourly, etc.
 *
 * The currently-open file handle is closed automatically when the driver is
 * destroyed or when rotation triggers a new file.
 */
final class RotatingStreamDriver implements SynchronousDriverInterface
{
    private ?string $currentDate = null;

    /** @var resource|null */
    private $stream;

    /**
     * @param string $pathPattern File path containing `{date}` as a placeholder.
     * @param LogFormatterInterface $formatter Formatter for each log line.
     * @param string $dateFormat Date format string used to build the file suffix.
     */
    public function __construct(
        private readonly string $pathPattern,
        private readonly LogFormatterInterface $formatter = new TextFormatter(),
        private readonly string $dateFormat = 'Y-m-d',
    ) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        $date = $logItem->dateTime->format($this->dateFormat);

        if ($date !== $this->currentDate) {
            $this->rotate($date);
        }

        /** @var resource $stream guaranteed non-null after rotate() or prior call */
        $stream = $this->stream;
        \fwrite($stream, $this->formatter->formatLog($logItem) . \PHP_EOL);
    }

    public function __destruct()
    {
        if ($this->stream !== null) {
            \fclose($this->stream);
            $this->stream = null;
        }
    }

    private function rotate(string $date): void
    {
        if ($this->stream !== null) {
            \fclose($this->stream);
            $this->stream = null;
        }

        $path = \str_replace('{date}', $date, $this->pathPattern);
        \set_error_handler(static fn(): bool => true);
        $stream = \fopen($path, 'a');
        \restore_error_handler();

        if ($stream === false) {
            throw new \RuntimeException(\sprintf('Cannot open log file for writing: %s', $path));
        }

        $this->stream = $stream;
        $this->currentDate = $date;
    }
}
