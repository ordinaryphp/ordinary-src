<?php

declare(strict_types=1);

namespace Ordinary\Log\Internal;

use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

/**
 * @internal Not part of the public API.
 */
final class LogGroup
{
    /** @var list<array{0: LogDriverInterface, 1: ?LogMatcherInterface}> */
    private array $drivers = [];

    public function __construct(
        public readonly string $id,
        private readonly ?LogMatcherInterface $matcher = null,
    ) {}

    public function matches(LogItemInterface $logItem): bool
    {
        return $this->matcher === null || $this->matcher->matches($logItem);
    }

    public function addDriver(LogDriverInterface $driver, ?LogMatcherInterface $matcher = null): void
    {
        $this->drivers[] = [$driver, $matcher];
    }

    /** @return list<array{0: LogDriverInterface, 1: ?LogMatcherInterface}> */
    public function getDrivers(): array
    {
        return $this->drivers;
    }
}
