<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsAll;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\Matcher\IsLevelOrLower;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsAll::class)]
final class IsAllTest extends TestCase
{
    #[Test]
    public function it_matches_when_all_matchers_pass(): void
    {
        $matcher = new IsAll(
            new IsLevelOrHigher(LogLevel::Warning),
            new IsLevelOrLower(LogLevel::Error),
        );

        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Warning, 'msg', new DateTimeImmutable())));
        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Critical, 'msg', new DateTimeImmutable())));
    }

    #[Test]
    public function it_always_matches_when_no_matchers_given(): void
    {
        $matcher = new IsAll();

        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Debug, 'msg', new DateTimeImmutable())));
    }
}
