<?php

declare(strict_types=1);

namespace Ordinary\Cli\Internal;

use Ordinary\Cli\Argument\ArgumentDefinition;
use Ordinary\Cli\Argument\ArgumentMode;
use Ordinary\Cli\Command\CommandDefinition;
use Ordinary\Cli\Exception\IllegalOptionCombinationException;
use Ordinary\Cli\Exception\InvalidArgumentValueException;
use Ordinary\Cli\Exception\MissingOptionValueException;
use Ordinary\Cli\Exception\MissingRequiredArgumentException;
use Ordinary\Cli\Exception\MissingRequiredOptionException;
use Ordinary\Cli\Exception\UnknownCommandException;
use Ordinary\Cli\Exception\UnknownOptionException;
use Ordinary\Cli\Input\ParsedInput;
use Ordinary\Cli\Option\OptionDefinition;
use Ordinary\Cli\Option\OptionRepeat;
use Ordinary\Cli\Option\OptionScope;
use Ordinary\Cli\Option\OptionType;

/**
 * @internal Recursive descent parser for the CLI argument vector.
 *
 * Parsing rules:
 * - --long value    : long option with space-separated value (only supported syntax)
 * - --long          : long flag (counts occurrences)
 * - -x value        : short option with space-separated value
 * - -xyz            : short flag cluster (all must be flags; error if any require a value)
 * - --              : terminates option processing; all subsequent tokens are positional
 * - Options before a subcommand token belong to the current level.
 * - Options after a subcommand token belong to that subcommand.
 * - --help / -h     : signals HelpRequestedException (caught by the dispatcher)
 * - --version / -V  : signals VersionRequestedException (caught by the dispatcher)
 */
final class Parser
{
    /**
     * @param list<string> $tokens
     * @param list<OptionDefinition> $inheritedDefs Global option definitions from ancestors.
     * @param array<string, int> $inheritedFlags Already-parsed global flag counts.
     * @param array<string, string> $inheritedValues Already-parsed global value options.
     * @param array<string, list<string>> $inheritedLists Already-parsed global list options.
     * @param list<string> $path Command name path from root to current.
     *
     * @throws HelpRequestedException
     * @throws VersionRequestedException
     * @throws UnknownOptionException
     * @throws UnknownCommandException
     * @throws MissingOptionValueException
     * @throws MissingRequiredOptionException
     * @throws MissingRequiredArgumentException
     * @throws IllegalOptionCombinationException
     * @throws InvalidArgumentValueException
     */
    public function parse(
        CommandDefinition $command,
        array $tokens,
        array $inheritedDefs = [],
        array $inheritedFlags = [],
        array $inheritedValues = [],
        array $inheritedLists = [],
        array $path = [],
    ): DispatchTarget {
        [$longMap, $shortMap] = $this->buildOptionMaps($command->options, $inheritedDefs);

        /** @var array<string, int> $flags */
        $flags = [];
        /** @var array<string, string> $values */
        $values = [];
        /** @var array<string, list<string>> $lists */
        $lists = [];
        /** @var list<string> $positionals */
        $positionals = [];
        $afterDashDash = false;

        $count = \count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i++];

            if ($afterDashDash) {
                $positionals[] = $token;
                continue;
            }

            if ($token === '--') {
                $afterDashDash = true;
                continue;
            }

            if ($token === '--help' || $token === '-h') {
                throw new HelpRequestedException($command, $path);
            }

            if ($token === '--version' || $token === '-V') {
                throw new VersionRequestedException();
            }

            if (\str_starts_with($token, '--')) {
                $longName = \substr($token, 2);

                if ($longName === '') {
                    throw new UnknownOptionException($command, $path, '--');
                }

                $def = $longMap[$longName] ?? null;

                if ($def === null) {
                    throw new UnknownOptionException($command, $path, '--' . $longName);
                }

                $this->applyOption($def, $tokens, $i, $command, $path, $flags, $values, $lists);
                continue;
            }

            if (\str_starts_with($token, '-') && \strlen($token) >= 2) {
                $cluster = \substr($token, 1);
                $this->processShortCluster($cluster, $shortMap, $longMap, $tokens, $i, $command, $path, $flags, $values, $lists);
                continue;
            }

            // Non-option token: check for subcommand dispatch.
            if (isset($command->subcommands[$token])) {
                $subDef = $command->subcommands[$token];
                $remaining = \array_slice($tokens, $i);

                [$newDefs, $newFlags, $newValues, $newLists] = $this->collectGlobals(
                    $command->options,
                    $flags,
                    $values,
                    $lists,
                    $inheritedDefs,
                    $inheritedFlags,
                    $inheritedValues,
                    $inheritedLists,
                );

                return $this->parse(
                    $subDef,
                    $remaining,
                    $newDefs,
                    $newFlags,
                    $newValues,
                    $newLists,
                    [...$path, $token],
                );
            }

            if ($command->subcommands !== []) {
                throw new UnknownCommandException($command, $path, $token);
            }

            $positionals[] = $token;
        }

        $this->validateRequiredOptions($command->options, $values, $lists, $command, $path);

        [$named, $variadic] = $this->assignArguments($command->arguments, $positionals, $command, $path);

        $mergedFlags = \array_merge($inheritedFlags, $flags);
        $mergedValues = \array_merge($inheritedValues, $values);
        $mergedLists = \array_merge($inheritedLists, $lists);

        $this->applyValueDefaults($command->options, $mergedValues, $mergedLists);

        return new DispatchTarget(
            command: $command,
            input: new ParsedInput($mergedFlags, $mergedValues, $mergedLists, $named, $variadic),
            path: $path,
        );
    }

    /**
     * Build long-name and short-char lookup maps for this command level.
     *
     * Inherited global definitions are merged in; local definitions take precedence
     * on name collision (local option shadow an inherited global of the same name).
     *
     * @param list<OptionDefinition> $local
     * @param list<OptionDefinition> $inherited
     *
     * @return array{array<string, OptionDefinition>, array<string, string>}
     */
    private function buildOptionMaps(array $local, array $inherited): array
    {
        /** @var array<string, OptionDefinition> $longMap */
        $longMap = [];
        /** @var array<string, string> $shortMap short char → long name */
        $shortMap = [];

        foreach ([...$inherited, ...$local] as $def) {
            $longMap[$def->long] = $def;

            if ($def->short !== null) {
                $shortMap[$def->short] = $def->long;
            }
        }

        return [$longMap, $shortMap];
    }

    /**
     * Apply a single option token to the accumulator arrays.
     *
     * @param list<string> $tokens
     * @param array<string, int> $flags
     * @param array<string, string> $values
     * @param array<string, list<string>> $lists
     * @param list<string> $path
     */
    private function applyOption(
        OptionDefinition $def,
        array $tokens,
        int &$i,
        CommandDefinition $command,
        array $path,
        array &$flags,
        array &$values,
        array &$lists,
    ): void {
        if ($def->type === OptionType::Flag) {
            $flags[$def->long] = ($flags[$def->long] ?? 0) + 1;
            return;
        }

        $value = $this->consumeValueToken($def, $tokens, $i, $command, $path);

        match ($def->repeat) {
            OptionRepeat::StoreLast  => $values[$def->long] = $value,
            OptionRepeat::StoreFirst => $values[$def->long] ??= $value,
            OptionRepeat::StoreList  => $lists[$def->long][] = $value,
        };
    }

    /**
     * @param array<string, string> $shortMap
     * @param array<string, OptionDefinition> $longMap
     * @param list<string> $tokens
     * @param array<string, int> $flags
     * @param array<string, string> $values
     * @param array<string, list<string>> $lists
     * @param list<string> $path
     */
    private function processShortCluster(
        string $cluster,
        array $shortMap,
        array $longMap,
        array $tokens,
        int &$i,
        CommandDefinition $command,
        array $path,
        array &$flags,
        array &$values,
        array &$lists,
    ): void {
        $length = \strlen($cluster);

        for ($j = 0; $j < $length; $j++) {
            $char = $cluster[$j];
            $long = $shortMap[$char] ?? null;

            if ($long === null) {
                throw new UnknownOptionException($command, $path, '-' . $char);
            }

            $def = $longMap[$long];

            if ($def->type === OptionType::Value && $length > 1) {
                throw new IllegalOptionCombinationException($command, $path, $cluster, $char);
            }

            $this->applyOption($def, $tokens, $i, $command, $path, $flags, $values, $lists);
        }
    }

    /**
     * Consume the next token as a value for a Value option.
     *
     * @param list<string> $tokens
     * @param list<string> $path
     */
    private function consumeValueToken(
        OptionDefinition $def,
        array $tokens,
        int &$i,
        CommandDefinition $command,
        array $path,
    ): string {
        if ($i >= \count($tokens)) {
            throw new MissingOptionValueException($command, $path, '--' . $def->long);
        }

        return $tokens[$i++];
    }

    /**
     * Collect global options from this level to pass down to subcommands.
     *
     * Returns merged defs, flags, values, and lists that include this level's globals
     * merged on top of the inherited context from ancestors.
     *
     * @param list<OptionDefinition> $localOptions
     * @param array<string, int> $parsedFlags
     * @param array<string, string> $parsedValues
     * @param array<string, list<string>> $parsedLists
     * @param list<OptionDefinition> $inheritedDefs
     * @param array<string, int> $inheritedFlags
     * @param array<string, string> $inheritedValues
     * @param array<string, list<string>> $inheritedLists
     *
     * @return array{list<OptionDefinition>, array<string, int>, array<string, string>, array<string, list<string>>}
     */
    private function collectGlobals(
        array $localOptions,
        array $parsedFlags,
        array $parsedValues,
        array $parsedLists,
        array $inheritedDefs,
        array $inheritedFlags,
        array $inheritedValues,
        array $inheritedLists,
    ): array {
        $newDefs = $inheritedDefs;
        $newFlags = $inheritedFlags;
        $newValues = $inheritedValues;
        $newLists = $inheritedLists;

        foreach ($localOptions as $def) {
            if ($def->scope !== OptionScope::Global) {
                continue;
            }

            $newDefs[] = $def;

            if ($def->type === OptionType::Flag) {
                if (isset($parsedFlags[$def->long])) {
                    $newFlags[$def->long] = ($newFlags[$def->long] ?? 0) + $parsedFlags[$def->long];
                }
            } elseif ($def->repeat === OptionRepeat::StoreList) {
                if (isset($parsedLists[$def->long])) {
                    $newLists[$def->long] = [
                        ...($newLists[$def->long] ?? []),
                        ...$parsedLists[$def->long],
                    ];
                }
            } elseif (isset($parsedValues[$def->long])) {
                $newValues[$def->long] = $parsedValues[$def->long];
            }
        }

        return [$newDefs, $newFlags, $newValues, $newLists];
    }

    /**
     * @param list<OptionDefinition> $options
     * @param array<string, string> $values
     * @param array<string, list<string>> $lists
     * @param list<string> $path
     */
    private function validateRequiredOptions(
        array $options,
        array $values,
        array $lists,
        CommandDefinition $command,
        array $path,
    ): void {
        foreach ($options as $def) {
            if (!$def->required) {
                continue;
            }

            if ($def->type === OptionType::Flag) {
                continue;
            }

            $provided = match ($def->repeat) {
                OptionRepeat::StoreList  => isset($lists[$def->long]),
                default                  => isset($values[$def->long]),
            };

            if (!$provided && $def->default === null) {
                throw new MissingRequiredOptionException($command, $path, $def->long);
            }
        }
    }

    /**
     * Assign positional tokens to declared argument definitions.
     *
     * @param list<ArgumentDefinition> $declarations
     * @param list<string> $positionals
     * @param list<string> $path
     *
     * @return array{array<string, string|null>, list<string>}
     */
    private function assignArguments(
        array $declarations,
        array $positionals,
        CommandDefinition $command,
        array $path,
    ): array {
        /** @var array<string, string|null> $named */
        $named = [];
        /** @var list<string> $variadic */
        $variadic = [];
        $pos = 0;

        foreach ($declarations as $argDef) {
            if ($argDef->mode === ArgumentMode::Variadic) {
                $variadic = \array_slice($positionals, $pos);
                $this->validateEnumValues($argDef, $variadic, $command, $path);
                $pos = \count($positionals);
                break;
            }

            $token = $positionals[$pos] ?? null;

            if ($token === null) {
                if ($argDef->mode === ArgumentMode::Required) {
                    throw new MissingRequiredArgumentException($command, $path, $argDef->name);
                }

                $named[$argDef->name] = null;
                continue;
            }

            $this->validateEnumValue($argDef, $token, $command, $path);
            $named[$argDef->name] = $token;
            $pos++;
        }

        // If no declarations, all positionals are collected as variadic.
        if ($declarations === []) {
            $variadic = $positionals;
        }

        return [$named, $variadic];
    }

    /**
     * @param list<string> $path
     */
    private function validateEnumValue(
        ArgumentDefinition $def,
        string $value,
        CommandDefinition $command,
        array $path,
    ): void {
        if ($def->enumClass === null) {
            return;
        }

        /** @var class-string<\BackedEnum> $enumClass */
        $enumClass = $def->enumClass;

        $allowed = \array_map(
            static fn(\BackedEnum $c): string => (string) $c->value,
            $enumClass::cases(),
        );

        if (!\in_array($value, $allowed, true)) {
            throw new InvalidArgumentValueException($command, $path, $def->name, $value, $allowed);
        }
    }

    /**
     * @param list<string> $values
     * @param list<string> $path
     */
    private function validateEnumValues(
        ArgumentDefinition $def,
        array $values,
        CommandDefinition $command,
        array $path,
    ): void {
        foreach ($values as $value) {
            $this->validateEnumValue($def, $value, $command, $path);
        }
    }

    /**
     * Apply declared defaults for value options that were not provided.
     *
     * @param list<OptionDefinition> $options
     * @param array<string, string> $values
     * @param array<string, list<string>> $lists
     */
    private function applyValueDefaults(
        array $options,
        array &$values,
        array &$lists,
    ): void {
        foreach ($options as $def) {
            if ($def->type === OptionType::Flag) {
                continue;
            }

            if ($def->default === null) {
                continue;
            }

            if ($def->repeat === OptionRepeat::StoreList) {
                $lists[$def->long] ??= [];
            } else {
                $values[$def->long] ??= (string) $def->default;
            }
        }
    }
}
