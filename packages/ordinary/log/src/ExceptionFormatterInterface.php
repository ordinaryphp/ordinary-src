<?php

declare(strict_types=1);

namespace Ordinary\Log;

interface ExceptionFormatterInterface
{
    public function formatException(\Throwable $throwable): string;
}
