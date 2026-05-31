<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsAny;
use Ordinary\Log\Matcher\IsLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsAny::class)]
final class IsAnyTest extends TestCase
{
    #[Test]
    public function it_matches_when_at_least_one_matcher_passes(): void
    {
        $matcher = new IsAny(
            new IsLevel(LogLevel::Debug),
            new IsLevel(LogLevel::Emergency),
        );

        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Debug, 'msg', new DateTimeImmutable())));
        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Emergency, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable())));
    }

    #[Test]
    public function it_never_matches_when_no_matchers_given(): void
    {
        $matcher = new IsAny();

        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Emergency, 'msg', new DateTimeImmutable())));
    }
}
