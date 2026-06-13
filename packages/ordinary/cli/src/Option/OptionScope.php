<?php

declare(strict_types=1);

namespace Ordinary\Cli\Option;

/** Controls whether a parsed option is propagated to descendant command handlers. */
enum OptionScope
{
    /** Parsed by and visible only to the command that declares it. */
    case Local;

    /**
     * Declared here; merged flat into ParsedInput for all descendant commands.
     *
     * From a handler's perspective, $input->flag('verbose') or $input->value('config')
     * works identically regardless of which ancestor declared the option.
     */
    case Global;
}
