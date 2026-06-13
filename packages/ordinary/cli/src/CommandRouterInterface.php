<?php

declare(strict_types=1);

namespace Ordinary\Cli;

use Ordinary\Cli\Command\CommandBuilder;
use Ordinary\Cli\Exception\InvalidArgumentException;
use Ordinary\Cli\Exception\LogicException;
use Ordinary\Cli\Option\OptionBuilder;

/**
 * Collects command and option registrations, then compiles them into a dispatcher.
 *
 * Register all commands and their options, then call compile() once.
 */
interface CommandRouterInterface
{
    /**
     * Declare an option available at the root command level.
     *
     * Call ->global() on the returned builder to make the option available to all
     * descendant commands without redeclaration.
     *
     * @throws InvalidArgumentException if $long is empty or contains spaces
     */
    public function option(string $long, ?string $short = null): OptionBuilder;

    /**
     * Register a top-level command and return its builder for configuration.
     */
    public function command(string $name): CommandBuilder;

    /**
     * Set a handler for the root command itself (invoked when no subcommand is provided).
     *
     * If no handler is set and no subcommand is matched, help text is printed and exit 0 is returned.
     *
     * @param \Closure(Input\ParsedInputInterface, Console): int $handler
     */
    public function handler(\Closure $handler): static;

    /**
     * Freeze the command tree and return the executable dispatcher.
     *
     * Validation performed at compile time:
     * - Every leaf command (no subcommands) must have a handler set.
     * - No command may declare both subcommands and positional arguments.
     * - No duplicate short option names at the same command level.
     *
     * @throws LogicException on any of the above violations
     */
    public function compile(): CommandDispatcherInterface;
}
