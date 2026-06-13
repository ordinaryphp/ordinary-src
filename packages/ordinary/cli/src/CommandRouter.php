<?php

declare(strict_types=1);

namespace Ordinary\Cli;

use Ordinary\Cli\Argument\ArgumentDefinition;
use Ordinary\Cli\Argument\ArgumentMode;
use Ordinary\Cli\Command\CommandBuilder;
use Ordinary\Cli\Command\CommandDefinition;
use Ordinary\Cli\Exception\LogicException;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Option\OptionBuilder;
use Ordinary\Cli\Option\OptionDefinition;

/**
 * Collects option and command registrations and compiles them into a CommandDispatcher.
 *
 * Register all options and commands, then call compile() exactly once.
 */
final class CommandRouter implements CommandRouterInterface
{
    /** @var list<OptionBuilder> */
    private array $options = [];

    /** @var array<string, CommandBuilder> */
    private array $commands = [];

    /** @var (\Closure(ParsedInputInterface, Console): int)|null */
    private ?\Closure $rootHandler = null;

    public function __construct(
        private readonly string $name,
        private readonly ?string $version = null,
    ) {}

    public function option(string $long, ?string $short = null): OptionBuilder
    {
        $builder = new OptionBuilder($long, $short);
        $this->options[] = $builder;
        return $builder;
    }

    public function command(string $name): CommandBuilder
    {
        $builder = new CommandBuilder($name);
        $this->commands[$name] = $builder;
        return $builder;
    }

    public function handler(\Closure $handler): static
    {
        $this->rootHandler = $handler;
        return $this;
    }

    public function compile(): CommandDispatcherInterface
    {
        $builtOptions = \array_map(
            static fn(OptionBuilder $b): OptionDefinition => $b->build(),
            $this->options,
        );

        $builtCommands = \array_map(
            static fn(CommandBuilder $b): CommandDefinition => $b->build(),
            $this->commands,
        );

        $root = new CommandDefinition(
            name: $this->name,
            description: '',
            options: $builtOptions,
            arguments: [],
            subcommands: $builtCommands,
            handler: $this->rootHandler,
        );

        $this->validateTree($root);

        return new CommandDispatcher(
            root: $root,
            appName: $this->name,
            version: $this->version,
        );
    }

    /** @throws LogicException */
    private function validateTree(CommandDefinition $command, string $parentPath = ''): void
    {
        $path = $parentPath !== '' ? $parentPath . ' ' . $command->name : $command->name;

        if ($command->subcommands !== [] && $command->arguments !== []) {
            throw new LogicException(
                \sprintf(
                    "Command '%s' declares both subcommands and positional arguments — only one is allowed",
                    $path,
                ),
            );
        }

        $this->validateOptionShortNames($command->options, $path);

        if ($command->subcommands === [] && !$command->handler instanceof \Closure) {
            throw new LogicException(
                \sprintf("Leaf command '%s' has no handler set; call ->handler(...) before compile()", $path),
            );
        }

        foreach ($command->subcommands as $sub) {
            $this->validateTree($sub, $path);
        }

        $this->validateArgumentOrder($command->arguments, $path);
    }

    /**
     * @param list<OptionDefinition> $options
     *
     * @throws LogicException
     */
    private function validateOptionShortNames(array $options, string $path): void
    {
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($options as $def) {
            if ($def->short === null) {
                continue;
            }

            if (isset($seen[$def->short])) {
                throw new LogicException(
                    \sprintf(
                        "Duplicate short option '-%s' at command '%s'",
                        $def->short,
                        $path,
                    ),
                );
            }

            $seen[$def->short] = true;
        }
    }

    /**
     * @param list<ArgumentDefinition> $arguments
     *
     * @throws LogicException
     */
    private function validateArgumentOrder(array $arguments, string $path): void
    {
        $variadicSeen = false;

        foreach ($arguments as $argDef) {
            if ($variadicSeen) {
                throw new LogicException(
                    \sprintf(
                        "Command '%s' declares an argument after a Variadic argument — Variadic must be last",
                        $path,
                    ),
                );
            }

            if ($argDef->mode === ArgumentMode::Variadic) {
                $variadicSeen = true;
            }
        }
    }
}
