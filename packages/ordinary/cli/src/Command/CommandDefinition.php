<?php

declare(strict_types=1);

namespace Ordinary\Cli\Command;

use Ordinary\Cli\Argument\ArgumentDefinition;
use Ordinary\Cli\Console;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Option\OptionDefinition;

/** Immutable, compiled snapshot of a command and its entire subtree. */
final readonly class CommandDefinition
{
    /**
     * @param list<OptionDefinition> $options
     * @param list<ArgumentDefinition> $arguments
     * @param array<string, CommandDefinition> $subcommands
     * @param (\Closure(ParsedInputInterface, Console): int)|null $handler
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $options,
        public array $arguments,
        public array $subcommands,
        public ?\Closure $handler,
    ) {}
}
