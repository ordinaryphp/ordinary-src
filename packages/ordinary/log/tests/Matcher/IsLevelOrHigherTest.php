<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsLevelOrHigher::class)]
final class IsLevelOrHigherTest extends TestCase
{
    /** @return iterable<string, array{LogLevel, LogLevel, bool}> */
    public static function levelProvider(): iterable
    {
        yield 'same level matches' => [LogLevel::Error, LogLevel::Error, true];
        yield 'higher level matches' => [LogLevel::Critical, LogLevel::Error, true];
        yield 'lower level does not match' => [LogLevel::Warning, LogLevel::Error, false];
        yield 'debug does not match error threshold' => [LogLevel::Debug, LogLevel::Error, false];
        yield 'emergency matches error threshold' => [LogLevel::Emergency, LogLevel::Error, true];
        yield 'debug matches debug threshold' => [LogLevel::Debug, LogLevel::Debug, true];
    }

    #[Test]
    #[DataProvider('levelProvider')]
    public function it_matches_the_given_level_and_above(
        LogLevel $itemLevel,
        LogLevel $threshold,
        bool $expected,
    ): void {
        $item = new LogEntry($itemLevel, 'msg', new DateTimeImmutable());
        $matcher = new IsLevelOrHigher($threshold);

        $this->assertSame($expected, $matcher->matches($item));
    }
}
