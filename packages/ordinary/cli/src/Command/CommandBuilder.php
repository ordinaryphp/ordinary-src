<?php

declare(strict_types=1);

namespace Ordinary\Cli\Command;

use Ordinary\Cli\Argument\ArgumentBuilder;
use Ordinary\Cli\Argument\ArgumentDefinition;
use Ordinary\Cli\Console;
use Ordinary\Cli\Exception\InvalidArgumentException;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Option\OptionBuilder;
use Ordinary\Cli\Option\OptionDefinition;

/**
 * Fluent builder for a command and its options, arguments, and subcommands.
 *
 * Returned by CommandRouter::command() and CommandBuilder::command().
 * Hold the reference to continue configuration after the initial call.
 */
final class CommandBuilder
{
    /** @var list<OptionBuilder> */
    private array $options = [];

    /** @var list<ArgumentBuilder> */
    private array $arguments = [];

    /** @var array<string, CommandBuilder> */
    private array $subcommands = [];

    /** @var (\Closure(ParsedInputInterface, Console): int)|null */
    private ?\Closure $handler = null;

    private string $description = '';

    public function __construct(private readonly string $name)
    {
        if ($name === '') {
            throw new InvalidArgumentException('Command name must not be empty');
        }
    }

    /**
     * Declare an option for this command.
     *
     * Returns the OptionBuilder so you can chain type, scope, description, and default.
     * The option is registered immediately; mutations on the returned builder are captured.
     *
     * @throws InvalidArgumentException if $long is empty or contains spaces
     */
    public function option(string $long, ?string $short = null): OptionBuilder
    {
        $builder = new OptionBuilder($long, $short);
        $this->options[] = $builder;
        return $builder;
    }

    /**
     * Declare a positional argument for this command.
     *
     * A command may declare positional arguments OR register subcommands, not both.
     * This constraint is enforced at compile() time.
     *
     * @throws InvalidArgumentException if $name is empty
     */
    public function argument(string $name): ArgumentBuilder
    {
        $builder = new ArgumentBuilder($name);
        $this->arguments[] = $builder;
        return $builder;
    }

    /**
     * Register a subcommand and return its builder for configuration.
     *
     * A command with subcommands may not also declare positional arguments.
     * This constraint is enforced at compile() time.
     */
    public function command(string $name): CommandBuilder
    {
        $child = new self($name);
        $this->subcommands[$name] = $child;
        return $child;
    }

    /**
     * Set the handler invoked when this command is dispatched.
     *
     * The closure receives the fully merged ParsedInput (including inherited globals)
     * and the Console instance, and must return an integer exit code (0 = success).
     *
     * @param \Closure(ParsedInputInterface, Console): int $handler
     */
    public function handler(\Closure $handler): static
    {
        $this->handler = $handler;
        return $this;
    }

    /** Human-readable description shown in auto-generated help text. */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /** @internal */
    public function build(): CommandDefinition
    {
        $builtOptions = \array_map(
            static fn(OptionBuilder $b): OptionDefinition => $b->build(),
            $this->options,
        );

        $builtArguments = \array_map(
            static fn(ArgumentBuilder $b): ArgumentDefinition => $b->build(),
            $this->arguments,
        );

        $builtSubcommands = \array_map(
            static fn(CommandBuilder $b): CommandDefinition => $b->build(),
            $this->subcommands,
        );

        return new CommandDefinition(
            name: $this->name,
            description: $this->description,
            options: $builtOptions,
            arguments: $builtArguments,
            subcommands: $builtSubcommands,
            handler: $this->handler,
        );
    }
}
