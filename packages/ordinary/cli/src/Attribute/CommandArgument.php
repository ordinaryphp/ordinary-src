<?php

declare(strict_types=1);

namespace Ordinary\Cli\Attribute;

use Ordinary\Cli\Argument\ArgumentMode;

/**
 * Declares a positional argument on a command registered via #[Command].
 *
 * Place alongside #[Command] on the same class, method, or function.
 * Repeatable — a single target may declare multiple arguments (in declaration order).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final readonly class CommandArgument
{
    /**
     * @param class-string<\BackedEnum>|null $enumClass When set, the parser validates
     *                                                  the token against all case values.
     */
    public function __construct(
        public string $name,
        public ArgumentMode $mode = ArgumentMode::Required,
        public ?string $enumClass = null,
        public string $description = '',
    ) {}
}
