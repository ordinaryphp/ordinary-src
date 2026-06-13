<?php

declare(strict_types=1);

namespace Ordinary\Cli;

use Ordinary\Cli\Command\CommandDefinition;
use Ordinary\Cli\Exception\ParseException;
use Ordinary\Cli\Internal\DispatchTarget;
use Ordinary\Cli\Internal\HelpFormatter;
use Ordinary\Cli\Internal\HelpRequestedException;
use Ordinary\Cli\Internal\Parser;
use Ordinary\Cli\Internal\VersionRequestedException;

/** Immutable compiled CLI dispatcher produced by CommandRouter::compile(). */
final readonly class CommandDispatcher implements CommandDispatcherInterface
{
    private Parser $parser;

    private HelpFormatter $formatter;

    public function __construct(
        private CommandDefinition $root,
        private string $appName,
        private ?string $version,
    ) {
        $this->parser = new Parser();
        $this->formatter = new HelpFormatter();
    }

    public function run(array $argv, Console $console): int
    {
        $tokens = \array_slice($argv, 1);

        try {
            $target = $this->parser->parse($this->root, $tokens);
        } catch (HelpRequestedException $e) {
            $console->output->write(
                $this->formatter->format($this->appName, $e->command, $e->path),
            );
            return ExitCode::Success->value;
        } catch (VersionRequestedException) {
            $console->output->writeln(
                $this->version !== null
                    ? $this->appName . ' ' . $this->version
                    : $this->appName,
            );
            return ExitCode::Success->value;
        } catch (ParseException $e) {
            $console->error->writeln('Error: ' . $e->getMessage());
            $console->error->write(
                $this->formatter->format($this->appName, $e->command, $e->commandPath),
            );
            return ExitCode::Usage->value;
        }

        return $this->dispatch($target, $console);
    }

    private function dispatch(DispatchTarget $target, Console $console): int
    {
        if (!$target->command->handler instanceof \Closure) {
            $console->output->write(
                $this->formatter->format($this->appName, $target->command, $target->path),
            );
            return ExitCode::Success->value;
        }

        return ($target->command->handler)($target->input, $console);
    }
}
