<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when the first positional token does not match any registered subcommand name. */
final class UnknownCommandException extends ParseException
{
    /**
     * @param list<string> $commandPath
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $token,
    ) {
        parent::__construct(
            $command,
            $commandPath,
            \sprintf(
                "Unknown command '%s' for '%s'",
                $token,
                $commandPath === [] ? $command->name : \implode(' ', $commandPath),
            ),
        );
    }
}
