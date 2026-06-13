<?php

declare(strict_types=1);

namespace Ordinary\Cli\Input;

/** Immutable result of parsing one command level's argument vector. */
final readonly class ParsedInput implements ParsedInputInterface
{
    /**
     * @param array<string, int> $flags long-name → occurrence count (includes inherited globals)
     * @param array<string, string> $values long-name → single string value (StoreLast / StoreFirst)
     * @param array<string, list<string>> $lists long-name → all values (StoreList)
     * @param array<string, string|null> $named declared positional arg name → captured value
     * @param list<string> $variadic remaining positional tokens
     */
    public function __construct(
        private array $flags,
        private array $values,
        private array $lists,
        private array $named,
        private array $variadic,
    ) {}

    public function flag(string $long): int
    {
        return $this->flags[$long] ?? 0;
    }

    public function value(string $long): ?string
    {
        return $this->values[$long] ?? null;
    }

    public function values(string $long): array
    {
        return $this->lists[$long] ?? [];
    }

    public function has(string $long): bool
    {
        return isset($this->flags[$long])
            || isset($this->values[$long])
            || isset($this->lists[$long]);
    }

    public function argument(string $name): ?string
    {
        return $this->named[$name] ?? null;
    }

    public function arguments(): array
    {
        return $this->variadic;
    }
}
