<?php

declare(strict_types=1);

namespace Ordinary\Cli\Option;

use Ordinary\Cli\Exception\InvalidArgumentException;

/**
 * Fluent builder for a single option declaration.
 *
 * Returned by CommandBuilder::option() and CommandRouter::option().
 * Configure via chained calls; the definition is frozen when the compiler reads it.
 */
final class OptionBuilder
{
    private OptionType $type        = OptionType::Flag;

    private OptionScope $scope      = OptionScope::Local;

    private OptionRepeat $repeat    = OptionRepeat::StoreLast;

    private string $description     = '';

    private ?string $default = null;

    private bool $required          = false;

    public function __construct(
        private readonly string $long,
        private readonly ?string $short,
    ) {
        $this->validateName($long, '--');

        if ($short !== null) {
            $this->validateName($short, '-');
        }
    }

    /** This option records occurrences as a count; no value token is consumed. */
    public function flag(): static
    {
        $this->type = OptionType::Flag;
        return $this;
    }

    /**
     * This option consumes the next token as its string value.
     *
     * Note: only space-separated syntax is supported (--option value).
     * The --option=value form is intentionally not supported.
     */
    public function value(): static
    {
        $this->type = OptionType::Value;
        return $this;
    }

    /** Make this option available to all descendant commands without redeclaration. */
    public function global(): static
    {
        $this->scope = OptionScope::Global;
        return $this;
    }

    /**
     * Control how repeated occurrences of this value option are handled.
     *
     * Only meaningful for OptionType::Value options.
     * StoreList values are accessed via ParsedInputInterface::values().
     */
    public function repeat(OptionRepeat $mode): static
    {
        $this->repeat = $mode;
        return $this;
    }

    /** Human-readable description shown in auto-generated help text. */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Value used when the option is absent from the argument vector.
     *
     * Only meaningful for OptionType::Value options.
     * Setting a default implies the option is not required.
     */
    public function default(string $default): static
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Mark this value option as required.
     *
     * A parse error occurs if the option is absent and no default is set.
     * Only meaningful for OptionType::Value options; flags are never required.
     */
    public function required(): static
    {
        $this->required = true;
        return $this;
    }

    /** @internal */
    public function build(): OptionDefinition
    {
        return new OptionDefinition(
            long: $this->long,
            short: $this->short,
            type: $this->type,
            scope: $this->scope,
            repeat: $this->repeat,
            description: $this->description,
            default: $this->default,
            required: $this->required,
        );
    }

    /** @throws InvalidArgumentException */
    private function validateName(string $name, string $prefix): void
    {
        if ($name === '') {
            throw new InvalidArgumentException(
                \sprintf('Option name passed to %s must not be empty', $prefix),
            );
        }

        if (\str_contains($name, ' ')) {
            throw new InvalidArgumentException(
                \sprintf("Option name '%s%s' must not contain spaces", $prefix, $name),
            );
        }
    }
}
