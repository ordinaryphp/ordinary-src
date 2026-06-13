<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when a required positional argument is absent from the argument vector. */
final class MissingRequiredArgumentException extends ParseException
{
    /**
     * @param list<string> $commandPath
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $argument,
    ) {
        parent::__construct(
            $command,
            $commandPath,
            \sprintf("Argument '<%s>' is required but was not provided", $argument),
        );
    }
}
