<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LevelFormatterInterface
{
    public function formatLevel(LogLevel $logLevel): string;
}
