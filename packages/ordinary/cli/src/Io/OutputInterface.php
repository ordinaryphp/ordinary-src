<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/** Writable stream abstraction for standard output and standard error. */
interface OutputInterface
{
    /** Write $content to the stream without appending a newline. */
    public function write(string $content): void;

    /** Write $content to the stream followed by a newline. */
    public function writeln(string $content = ''): void;
}
