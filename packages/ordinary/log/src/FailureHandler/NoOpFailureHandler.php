<?php

declare(strict_types=1);

namespace Ordinary\Log\FailureHandler;

use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;

/**
 * Silently discards all log dispatch failures.
 *
 * Use this when you explicitly want no-op failure handling, as an alternative
 * to leaving the failure handler unset (which causes exceptions to propagate).
 */
final class NoOpFailureHandler implements LogFailureHandlerInterface
{
    public function handleLogFailure(LogFailureExceptionInterface $e): void {}
}
