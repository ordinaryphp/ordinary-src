<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when a positional argument value fails its enum constraint. */
final class InvalidArgumentValueException extends ParseException
{
    /**
     * @param list<string> $commandPath
     * @param list<string> $allowed
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $argument,
        public readonly string $value,
        public readonly array $allowed,
    ) {
        parent::__construct(
            $command,
            $commandPath,
            \sprintf(
                "Argument '<%s>' received '%s'; expected one of: %s",
                $argument,
                $value,
                \implode(', ', $allowed),
            ),
        );
    }
}
