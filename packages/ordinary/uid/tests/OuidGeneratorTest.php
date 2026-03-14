<?php

declare(strict_types=1);

namespace Ordinary\Uid\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Uid\OuidGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Random\Engine\Xoshiro256StarStar;

#[CoversClass(OuidGenerator::class)]
final class OuidGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_ouid_with_namespace(): void
    {
        $clock = $this->createClock();
        $generator = new OuidGenerator('TESTAPP', $clock);
        $ouid = $generator->generate();

        self::assertSame('TESTAPP', $ouid->namespace);
    }

    #[Test]
    public function it_generates_ouid_with_current_time_from_clock(): void
    {
        $fixedTime = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'));
        $clock = $this->createClock($fixedTime);

        $generator = new OuidGenerator('TESTAPP', $clock);
        $ouid = $generator->generate();

        self::assertSame($fixedTime->format('U'), $ouid->datetime->format('U'));
    }

    #[Test]
    public function it_generates_unique_ouids(): void
    {
        $clock = $this->createClock();
        $generator = new OuidGenerator('TESTAPP', $clock);
        $ouid1 = $generator->generate();
        $ouid2 = $generator->generate();

        self::assertNotSame($ouid1->value, $ouid2->value);
    }

    #[Test]
    public function it_uses_deterministic_random_engine(): void
    {
        $fixedTime = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'));
        $clock = $this->createClock($fixedTime);
        $engine = new Xoshiro256StarStar(12345);

        $generator = new OuidGenerator('TESTAPP', $clock, $engine);
        $ouid = $generator->generate();

        self::assertNotEmpty($ouid->randomBytes);
        self::assertSame(4, \strlen($ouid->randomBytes));
    }

    private function createClock(?DateTimeImmutable $fixedTime = null): ClockInterface
    {
        return new class($fixedTime) implements ClockInterface {
            public function __construct(private readonly ?DateTimeImmutable $fixedTime = null)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->fixedTime ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
            }
        };
    }
}
