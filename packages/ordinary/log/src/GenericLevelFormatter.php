<?php

declare(strict_types=1);

namespace Ordinary\Log;

final class GenericLevelFormatter implements LevelFormatterInterface
{
    public function __construct(
        private readonly bool $uppercase = false,
    ) {}

    public function formatLevel(LogLevel $logLevel): string
    {
        $name = $logLevel->getFullName();

        return $this->uppercase ? \strtoupper($name) : $name;
    }
}
