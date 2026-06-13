<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when a required option is absent from the argument vector and has no default. */
final class MissingRequiredOptionException extends ParseException
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
            \sprintf("Option '--%s' is required but was not provided", $option),
        );
    }
}
