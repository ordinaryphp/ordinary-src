<?php

declare(strict_types=1);

namespace Ordinary\Cli\Internal;

use Ordinary\Cli\Command\CommandDefinition;
use Ordinary\Cli\Input\ParsedInput;

/** @internal Result of a successful parse; carries the matched command and its input. */
final readonly class DispatchTarget
{
    /**
     * @param list<string> $path Command name path from root, e.g. ['db', 'migrate'].
     */
    public function __construct(
        public CommandDefinition $command,
        public ParsedInput $input,
        public array $path,
    ) {}
}
