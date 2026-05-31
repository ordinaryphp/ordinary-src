<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\Matcher\IsNot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsNot::class)]
final class IsNotTest extends TestCase
{
    #[Test]
    public function it_inverts_the_wrapped_matcher(): void
    {
        $matcher = new IsNot(new IsLevelOrHigher(LogLevel::Error));

        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Debug, 'msg', new DateTimeImmutable())));
        $this->assertTrue($matcher->matches(new LogEntry(LogLevel::Warning, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new LogEntry(LogLevel::Emergency, 'msg', new DateTimeImmutable())));
    }
}
