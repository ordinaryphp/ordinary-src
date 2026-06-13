<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/** Wraps a PHP stream resource as an OutputInterface (typically STDOUT or STDERR). */
final readonly class StreamOutput implements OutputInterface
{
    /** @param resource $stream */
    public function __construct(private mixed $stream) {}

    public function write(string $content): void
    {
        \fwrite($this->stream, $content);
    }

    public function writeln(string $content = ''): void
    {
        \fwrite($this->stream, $content . "\n");
    }
}
