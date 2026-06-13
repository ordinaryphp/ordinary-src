<?php

declare(strict_types=1);

namespace Ordinary\Cli\Option;

/**
 * Controls how a Value option behaves when it appears more than once in the argument vector.
 *
 * Only applies to OptionType::Value options. Flag options always count occurrences.
 */
enum OptionRepeat
{
    /** Keep the last provided value. Subsequent occurrences overwrite the previous. Default. */
    case StoreLast;

    /** Keep the first provided value. Subsequent occurrences are consumed but discarded. */
    case StoreFirst;

    /** Collect all occurrences into a list. Access via ParsedInputInterface::values(). */
    case StoreList;
}
