<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/**
 * In-memory OutputInterface for use in tests.
 *
 * Captures all written content; retrieve it via content().
 */
final class BufferedOutput implements OutputInterface
{
    private string $buffer = '';

    public function write(string $content): void
    {
        $this->buffer .= $content;
    }

    public function writeln(string $content = ''): void
    {
        $this->buffer .= $content . "\n";
    }

    /** Return everything written since construction or the last clear(). */
    public function content(): string
    {
        return $this->buffer;
    }

    /** Reset the internal buffer. */
    public function clear(): void
    {
        $this->buffer = '';
    }
}
