<?php

declare(strict_types=1);

namespace Ordinary\Uid;

use DateTimeZone;
use Psr\Clock\ClockInterface;
use Random\Engine;
use Random\Randomizer;

/**
 * Service for generating new OUIDs.
 */
final readonly class OuidGenerator
{
    private Randomizer $randomizer;

    /**
     * @param non-empty-string $namespace
     */
    public function __construct(
        private string $namespace,
        private ClockInterface $clock,
        ?Engine $engine = null,
    ) {
        $this->randomizer = new Randomizer($engine);
    }

    /**
     * Generate a new OUID.
     */
    public function generate(): Ouid
    {
        $datetime = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        /** @var non-empty-string $randomBytes */
        $randomBytes = $this->randomizer->getBytes(4);

        return Ouid::create($this->namespace, $datetime, $randomBytes);
    }
}
