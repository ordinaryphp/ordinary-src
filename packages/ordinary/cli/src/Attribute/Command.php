<?php

declare(strict_types=1);

namespace Ordinary\Cli\Attribute;

/**
 * Declares a CLI command on a class, method, or function.
 *
 * On a class: the class represents the named command. If the class has __invoke,
 * that method is the handler. Methods with their own #[Command] attribute become
 * subcommands automatically — no explicit parent: [] needed on those methods.
 *
 * On a method: the method is the handler for the named command. If the containing
 * class also carries #[Command], the class-level command name is the implicit parent.
 * Otherwise, specify parent: [] explicitly.
 *
 * On a function: the function is the handler for the named command.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class Command
{
    /**
     * @param list<string> $parent Path of command names from the root. Ignored when the
     *                             attribute is on a method inside a class that also carries
     *                             #[Command] — the class name becomes the implicit parent.
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $parent = [],
    ) {}
}
