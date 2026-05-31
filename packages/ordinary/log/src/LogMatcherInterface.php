<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogMatcherInterface
{
    public function matches(LogEntryInterface $logItem): bool;
}
