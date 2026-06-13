<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/** Readable stream abstraction for standard input. */
interface InputInterface
{
    /** Whether the underlying stream is an interactive TTY. */
    public bool $isInteractive { get; }

    /** Read up to $length bytes; returns '' on EOF. */
    public function read(int $length = 8192): string;

    /** Read one line, stripping the trailing newline; returns null on EOF. */
    public function readLine(): ?string;
}
