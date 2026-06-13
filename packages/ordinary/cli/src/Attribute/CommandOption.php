<?php

declare(strict_types=1);

namespace Ordinary\Cli\Attribute;

use Ordinary\Cli\Option\OptionRepeat;
use Ordinary\Cli\Option\OptionScope;
use Ordinary\Cli\Option\OptionType;

/**
 * Declares an option on a command registered via #[Command].
 *
 * Place alongside #[Command] on the same class, method, or function.
 * Repeatable — a single target may declare multiple options.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final readonly class CommandOption
{
    public function __construct(
        public string $long,
        public ?string $short = null,
        public OptionType $type = OptionType::Flag,
        public OptionScope $scope = OptionScope::Local,
        public OptionRepeat $repeat = OptionRepeat::StoreLast,
        public string $description = '',
        public ?string $default = null,
        public bool $required = false,
    ) {}
}
