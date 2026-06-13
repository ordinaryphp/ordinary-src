<?php

declare(strict_types=1);

namespace Ordinary\Cli\Argument;

use Ordinary\Cli\Exception\InvalidArgumentException;

/**
 * Fluent builder for a single positional argument declaration.
 *
 * Returned by CommandBuilder::argument().
 */
final class ArgumentBuilder
{
    private ArgumentMode $mode         = ArgumentMode::Required;

    /** @var class-string<\BackedEnum>|null */
    private ?string $enumClass         = null;

    private string $description        = '';

    public function __construct(private readonly string $name)
    {
        if ($name === '') {
            throw new InvalidArgumentException('Argument name must not be empty');
        }
    }

    /** The argument must be provided; a parse error occurs if it is absent. */
    public function required(): static
    {
        $this->mode = ArgumentMode::Required;
        return $this;
    }

    /** The argument may be absent; ParsedInputInterface::argument() returns null. */
    public function optional(): static
    {
        $this->mode = ArgumentMode::Optional;
        return $this;
    }

    /**
     * Consume all remaining positional tokens into a list.
     *
     * Must be declared last among a command's arguments.
     */
    public function variadic(): static
    {
        $this->mode = ArgumentMode::Variadic;
        return $this;
    }

    /**
     * Restrict this argument to the string values of a BackedEnum.
     *
     * The parser validates the provided token against all case values and throws
     * InvalidArgumentValueException on mismatch. ParsedInputInterface::argument()
     * still returns the raw string — call BackedEnum::from() safely after parsing.
     *
     * Pass the class name of a string- or int-backed enum (e.g. MyEnum::class).
     *
     * @throws InvalidArgumentException if $enumClass is not a BackedEnum
     */
    public function enum(string $enumClass): static
    {
        if (!\is_a($enumClass, \BackedEnum::class, true)) {
            throw new InvalidArgumentException(
                \sprintf('"%s" must be a BackedEnum class', $enumClass),
            );
        }

        /** @var class-string<\BackedEnum> $enumClass */
        $this->enumClass = $enumClass;
        return $this;
    }

    /** Human-readable description shown in auto-generated help text. */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /** @internal */
    public function build(): ArgumentDefinition
    {
        return new ArgumentDefinition(
            name: $this->name,
            mode: $this->mode,
            enumClass: $this->enumClass,
            description: $this->description,
        );
    }
}
