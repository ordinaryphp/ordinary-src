<?php

declare(strict_types=1);

namespace Ordinary\Cli\Internal;

use Ordinary\Cli\Command\CommandDefinition;

/**
 * @internal Thrown by the parser when --help or -h is encountered.
 *           Caught by CommandDispatcher::run() to print help and exit 0.
 */
final class HelpRequestedException extends \RuntimeException
{
    /**
     * @param list<string> $path
     */
    public function __construct(
        public readonly CommandDefinition $command,
        public readonly array $path,
    ) {
        parent::__construct('Help requested');
    }
}
