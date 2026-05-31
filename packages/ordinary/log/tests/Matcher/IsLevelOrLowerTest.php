<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsLevelOrLower;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsLevelOrLower::class)]
final class IsLevelOrLowerTest extends TestCase
{
    /** @return iterable<string, array{LogLevel, LogLevel, bool}> */
    public static function levelProvider(): iterable
    {
        yield 'same level matches' => [LogLevel::Warning, LogLevel::Warning, true];
        yield 'lower level matches' => [LogLevel::Info, LogLevel::Warning, true];
        yield 'higher level does not match' => [LogLevel::Error, LogLevel::Warning, false];
        yield 'debug matches warning ceiling' => [LogLevel::Debug, LogLevel::Warning, true];
        yield 'emergency does not match warning ceiling' => [LogLevel::Emergency, LogLevel::Warning, false];
        yield 'emergency matches emergency ceiling' => [LogLevel::Emergency, LogLevel::Emergency, true];
    }

    #[Test]
    #[DataProvider('levelProvider')]
    public function it_matches_the_given_level_and_below(
        LogLevel $itemLevel,
        LogLevel $ceiling,
        bool $expected,
    ): void {
        $item = new LogEntry($itemLevel, 'msg', new DateTimeImmutable());
        $matcher = new IsLevelOrLower($ceiling);

        $this->assertSame($expected, $matcher->matches($item));
    }
}
