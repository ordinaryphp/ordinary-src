<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/** Wraps a PHP stream resource as an InputInterface (typically STDIN). */
final class StreamInput implements InputInterface
{
    public bool $isInteractive {
        get => \stream_isatty($this->stream);
    }

    /** @param resource $stream */
    public function __construct(private readonly mixed $stream) {}

    public function read(int $length = 8192): string
    {
        $result = \fread($this->stream, \max(1, $length));
        return $result === false ? '' : $result;
    }

    public function readLine(): ?string
    {
        $line = \fgets($this->stream);

        if ($line === false) {
            return null;
        }

        return \rtrim($line, "\n\r");
    }
}
