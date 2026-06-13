<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/** Thrown when an option token appears that was not declared on the matched command. */
final class UnknownOptionException extends ParseException
{
    /**
     * @param list<string> $commandPath
     */
    public function __construct(
        CommandDefinition $command,
        array $commandPath,
        public readonly string $token,
        string $detail = '',
    ) {
        $suffix = $detail !== '' ? ': ' . $detail : '';
        parent::__construct(
            $command,
            $commandPath,
            \sprintf(
                "Unknown option '%s' for command '%s'%s",
                $token,
                $commandPath === [] ? $command->name : \implode(' ', $commandPath),
                $suffix,
            ),
        );
    }
}
