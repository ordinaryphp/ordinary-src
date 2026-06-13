<?php

declare(strict_types=1);

namespace Ordinary\Cli\Argument;

/** Controls whether a positional argument must be present and how many tokens it consumes. */
enum ArgumentMode
{
    /** The argument must be provided; a parse error occurs if it is absent. */
    case Required;

    /** The argument may be absent; ParsedInputInterface::argument() returns null when missing. */
    case Optional;

    /**
     * Consumes all remaining positional tokens into a list.
     *
     * Must be the last declared argument on a command.
     * Access via ParsedInputInterface::arguments().
     */
    case Variadic;
}
