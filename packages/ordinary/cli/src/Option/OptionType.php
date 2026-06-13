<?php

declare(strict_types=1);

namespace Ordinary\Cli\Option;

/** Determines whether an option consumes a value token from the argument vector. */
enum OptionType
{
    /**
     * --verbose / -v — records the number of times this option appears; no value consumed.
     *
     * Note: the `=` value syntax is not supported. Only space-separated values are accepted.
     */
    case Flag;

    /**
     * --config value / -c value — consumes the next token as the option value.
     *
     * Note: the `=` value syntax (--config=value) is not supported.
     * The value must follow as a separate token: --config value.
     */
    case Value;
}
