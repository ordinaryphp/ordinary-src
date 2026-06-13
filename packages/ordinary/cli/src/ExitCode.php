<?php

declare(strict_types=1);

namespace Ordinary\Cli;

/** Well-known POSIX exit codes. Handlers return int; use these cases for readability. */
enum ExitCode: int
{
    /** Execution completed without errors. */
    case Success = 0;

    /** General runtime error. */
    case Error = 1;

    /** Usage error: unknown option, missing required option or argument, unknown command. */
    case Usage = 2;
}
