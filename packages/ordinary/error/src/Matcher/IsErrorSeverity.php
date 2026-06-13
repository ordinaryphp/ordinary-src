<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Matches {@see \ErrorException} instances whose severity flag overlaps any of
 * the given PHP error-level constants (e.g. `E_WARNING`, `E_NOTICE`).
 *
 * Always returns `false` for throwables that are not `\ErrorException`.
 */
final readonly class IsErrorSeverity implements ThrowableMatcherInterface
{
    private int $severityMask;

    /**
     * @param int $severities One or more PHP error-level constants (E_WARNING, E_NOTICE, …).
     *                        Multiple values are OR-combined into a bitmask.
     */
    public function __construct(int ...$severities)
    {
        $mask = 0;

        foreach ($severities as $severity) {
            $mask |= $severity;
        }

        $this->severityMask = $mask;
    }

    public function matches(\Throwable $throwable): bool
    {
        return $throwable instanceof \ErrorException
            && ($throwable->getSeverity() & $this->severityMask) !== 0;
    }
}
