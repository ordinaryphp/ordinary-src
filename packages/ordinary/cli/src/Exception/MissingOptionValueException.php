<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when a value option appears as the last token with no following value. */
final class MissingOptionValueException extends ParseException
{
    /**
     * @param list<string> $commandPath
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $option,
    ) {
        parent::__construct(
            $command,
            $commandPath,
            \sprintf("Option '%s' requires a value but none was provided", $option),
        );
    }
}
