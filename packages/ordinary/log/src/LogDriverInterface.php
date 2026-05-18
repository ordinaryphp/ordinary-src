<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface LogDriverInterface
{
    public function handleLog(LogItemInterface $logItem): void;
}
