<?php

declare(strict_types=1);

namespace Ordinary\Cli\Input;

/**
 * The typed result of parsing one command level's argument vector.
 *
 * Passed to every command handler as the first argument.
 * Global options declared on ancestor commands are merged flat — handlers
 * access them identically to locally-declared options.
 */
interface ParsedInputInterface
{
    /**
     * Count of how many times this flag option appeared in the argument vector.
     *
     * Returns 0 if the flag was never provided. A non-zero value is truthy.
     * Calling this on a value option always returns 0.
     *
     * Supports counting repeated occurrences: -vvv or --verbose --verbose --verbose both yield 3.
     */
    public function flag(string $long): int;

    /**
     * String value for this value option.
     *
     * Returns the declared default when the option was not explicitly provided,
     * or null if no default was configured. Calling this on a flag option returns null.
     *
     * For StoreList options, this returns null; use values() instead.
     */
    public function value(string $long): ?string;

    /**
     * All values captured for a StoreList value option.
     *
     * Returns an empty list when the option was not provided.
     * For StoreLast / StoreFirst options, this always returns an empty list.
     *
     * @return list<string>
     */
    public function values(string $long): array;

    /**
     * Returns true if this option was provided or has a non-null default.
     *
     * Works for both flag and value options.
     *
     * @assert-if-true return true
     */
    public function has(string $long): bool;

    /**
     * Named positional argument value by its declaration name.
     *
     * Returns null if the argument was declared Optional and not provided.
     * Not meaningful for Variadic arguments; use arguments() instead.
     */
    public function argument(string $name): ?string;

    /**
     * All positional tokens for a Variadic argument, or the full ordered
     * list of positionals when no named arguments were declared.
     *
     * @return list<string>
     */
    public function arguments(): array;
}
