<?php

declare(strict_types=1);

namespace Ordinary\Cli\Internal;

use Ordinary\Cli\Argument\ArgumentMode;
use Ordinary\Cli\Command\CommandDefinition;
use Ordinary\Cli\Option\OptionType;

/** @internal Generates plain-text help output from a CommandDefinition. */
final class HelpFormatter
{
    private const int COLUMN_WIDTH = 26;

    /**
     * @param list<string> $path
     */
    public function format(string $appName, CommandDefinition $command, array $path): string
    {
        $lines = [];

        $lines[] = 'Usage: ' . $this->buildUsageLine($appName, $command, $path);

        if ($command->description !== '') {
            $lines[] = '';
            $lines[] = $command->description;
        }

        if ($command->options !== []) {
            $lines[] = '';
            $lines[] = 'Options:';
            foreach ($command->options as $opt) {
                $left = $opt->short !== null
                    ? '-' . $opt->short . ', --' . $opt->long
                    : '    --' . $opt->long;

                if ($opt->type === OptionType::Value) {
                    $left .= ' <value>';
                }

                $right = $opt->description;
                if ($opt->default !== null && $opt->default !== false) {
                    $right .= ' (default: ' . $opt->default . ')';
                }

                $lines[] = '  ' . \str_pad($left, self::COLUMN_WIDTH) . $right;
            }
        }

        if ($command->subcommands !== []) {
            $lines[] = '';
            $lines[] = 'Commands:';
            foreach ($command->subcommands as $sub) {
                $lines[] = '  ' . \str_pad($sub->name, self::COLUMN_WIDTH) . $sub->description;
            }
        } elseif ($command->arguments !== []) {
            $lines[] = '';
            $lines[] = 'Arguments:';
            foreach ($command->arguments as $arg) {
                $desc = $arg->description;
                if ($arg->enumClass !== null) {
                    /** @var class-string<\BackedEnum> $enumClass */
                    $enumClass = $arg->enumClass;
                    $cases = \array_map(
                        static fn(\BackedEnum $c): string => (string) $c->value,
                        $enumClass::cases(),
                    );
                    $desc .= ' (one of: ' . \implode(', ', $cases) . ')';
                }

                $lines[] = '  ' . \str_pad($arg->name, self::COLUMN_WIDTH) . $desc;
            }
        }

        $lines[] = '';
        $lines[] = '  ' . \str_pad('-h, --help', self::COLUMN_WIDTH) . 'Show this help message and exit';

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $path
     */
    private function buildUsageLine(string $appName, CommandDefinition $command, array $path): string
    {
        $parts = [$appName, ...$path];
        $usage = \implode(' ', $parts);

        if ($command->options !== []) {
            $usage .= ' [options]';
        }

        if ($command->subcommands !== []) {
            $usage .= ' <command>';
        } else {
            foreach ($command->arguments as $arg) {
                $usage .= match ($arg->mode) {
                    ArgumentMode::Required => ' <' . $arg->name . '>',
                    ArgumentMode::Optional => ' [<' . $arg->name . '>]',
                    ArgumentMode::Variadic => ' [<' . $arg->name . '>...]',
                };
            }
        }

        return $usage;
    }
}
