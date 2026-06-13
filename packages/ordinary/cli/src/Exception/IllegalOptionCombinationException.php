<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when a value option appears inside a short-option stack (e.g. -vc value). */
final class IllegalOptionCombinationException extends ParseException
{
    /**
     * @param list<string> $commandPath
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $cluster,
        public readonly string $valueOptionChar,
    ) {
        parent::__construct(
            $command,
            $commandPath,
            \sprintf(
                "Short option '-%s' requires a value and cannot be stacked in '-%s'",
                $valueOptionChar,
                $cluster,
            ),
        );
    }
}
