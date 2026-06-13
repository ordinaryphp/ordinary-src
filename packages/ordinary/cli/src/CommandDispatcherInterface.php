<?php

declare(strict_types=1);

namespace Ordinary\Cli;

/**
 * Immutable compiled CLI dispatcher.
 *
 * Produced by CommandRouter::compile(). Parse errors are caught internally,
 * written to $console->error, and returned as ExitCode::Usage — they are
 * never surfaced to the caller of run().
 */
interface CommandDispatcherInterface
{
    /**
     * Parse $argv, route to the matched command, invoke its handler, and return the exit code.
     *
     * Pass $_SERVER['argv'] directly; the program name at index 0 is stripped internally.
     * --help at any command level writes help text to $console->output and returns 0.
     * --version (when a version string was provided) writes the version and returns 0.
     *
     * @param list<string> $argv
     */
    public function run(array $argv, Console $console): int;
}
