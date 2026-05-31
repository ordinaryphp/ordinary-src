<?php

declare(strict_types=1);

namespace Ordinary\Log;

final readonly class LevelFormatter implements LevelFormatterInterface
{
    public function __construct(
        private bool $uppercase = false,
    ) {}

    public function formatLevel(LogLevel $logLevel): string
    {
        $name = $logLevel->getFullName();

        return $this->uppercase ? \strtoupper($name) : $name;
    }
}
