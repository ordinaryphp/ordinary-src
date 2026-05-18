<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\IsLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsLevel::class)]
final class IsLevelTest extends TestCase
{
    #[Test]
    public function it_matches_the_exact_level(): void
    {
        $matcher = new IsLevel(LogLevel::Error);

        $this->assertTrue($matcher->matches(new GenericLogItem(LogLevel::Error, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new GenericLogItem(LogLevel::Warning, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new GenericLogItem(LogLevel::Critical, 'msg', new DateTimeImmutable())));
    }

    #[Test]
    public function it_matches_any_of_multiple_levels(): void
    {
        $matcher = new IsLevel(LogLevel::Warning, LogLevel::Error, LogLevel::Critical);

        $this->assertTrue($matcher->matches(new GenericLogItem(LogLevel::Warning, 'msg', new DateTimeImmutable())));
        $this->assertTrue($matcher->matches(new GenericLogItem(LogLevel::Error, 'msg', new DateTimeImmutable())));
        $this->assertTrue($matcher->matches(new GenericLogItem(LogLevel::Critical, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable())));
        $this->assertFalse($matcher->matches(new GenericLogItem(LogLevel::Emergency, 'msg', new DateTimeImmutable())));
    }

    #[Test]
    public function it_never_matches_when_no_levels_given(): void
    {
        $matcher = new IsLevel();

        $this->assertFalse($matcher->matches(new GenericLogItem(LogLevel::Debug, 'msg', new DateTimeImmutable())));
    }
}
