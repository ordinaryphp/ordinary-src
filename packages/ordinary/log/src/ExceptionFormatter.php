<?php

declare(strict_types=1);

namespace Ordinary\Log;

final readonly class ExceptionFormatter implements ExceptionFormatterInterface
{
    public function __construct(
        private bool $includeTrace = true,
        private bool $includePrevious = true,
        private int $traceDepth = 10,
    ) {}

    public function formatException(\Throwable $throwable): string
    {
        $parts = [];

        foreach ($this->collectChain($throwable) as $depth => $current) {
            if ($depth > 0) {
                $parts[] = '';
                $parts[] = 'Caused by:';
            }

            $parts[] = \sprintf(
                '%s: %s (code %d) in %s:%d',
                $current::class,
                $current->getMessage(),
                $current->getCode(),
                $current->getFile(),
                $current->getLine(),
            );

            if ($this->includeTrace) {
                $frames = $current->getTrace();
                $shown = \array_slice($frames, 0, $this->traceDepth);

                foreach ($shown as $i => $frame) {
                    $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
                    $location = isset($frame['file'])
                        ? $frame['file'] . ':' . ($frame['line'] ?? 0)
                        : '[internal]';

                    $parts[] = \sprintf('  #%d %s in %s', $i, $call, $location);
                }

                $remaining = \count($frames) - $this->traceDepth;

                if ($remaining > 0) {
                    $parts[] = \sprintf('  ... %d more frame(s)', $remaining);
                }
            }
        }

        return \implode(\PHP_EOL, $parts);
    }

    /** @return list<\Throwable> */
    private function collectChain(\Throwable $throwable): array
    {
        if (!$this->includePrevious) {
            return [$throwable];
        }

        $chain = [$throwable];
        $previous = $throwable->getPrevious();

        while ($previous instanceof \Throwable) {
            $chain[] = $previous;
            $previous = $previous->getPrevious();
        }

        return $chain;
    }
}
