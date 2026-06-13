<?php

declare(strict_types=1);

namespace Ordinary\Cli\Option;

/** Immutable declaration of a single CLI option. */
final readonly class OptionDefinition
{
    public function __construct(
        public string $long,
        public ?string $short,
        public OptionType $type,
        public OptionScope $scope,
        public OptionRepeat $repeat,
        public string $description,
        public string|bool|null $default,
        /** Whether this option must be provided when no default is set. */
        public bool $required,
    ) {}
}
