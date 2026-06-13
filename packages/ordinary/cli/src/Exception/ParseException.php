<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

use Ordinary\Cli\Command\CommandDefinition;

/**
 * Base class for all errors detected while parsing the argument vector.
 *
 * The dispatcher catches every subtype, writes to stderr, and returns ExitCode::Usage.
 * Callers who invoke CommandDispatcher::run() never receive this exception directly.
 */
abstract class ParseException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param list<string> $commandPath Path of command names from root to the failing command.
     */
    public function __construct(
        public readonly CommandDefinition $command,
        public readonly array $commandPath,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
