<?php

declare(strict_types=1);

namespace Ordinary\Cli\Argument;

/** Immutable declaration of a single positional argument. */
final readonly class ArgumentDefinition
{
    /**
     * @param class-string<\BackedEnum>|null $enumClass When set, the parser validates
     *                                                  the token against all case values.
     */
    public function __construct(
        public string $name,
        public ArgumentMode $mode,
        public ?string $enumClass,
        public string $description,
    ) {}
}
