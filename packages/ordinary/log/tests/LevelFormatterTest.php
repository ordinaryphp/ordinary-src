<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use Ordinary\Log\LevelFormatter;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LevelFormatter::class)]
final class LevelFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_level_in_lowercase_by_default(): void
    {
        $formatter = new LevelFormatter();

        $this->assertSame('emergency', $formatter->formatLevel(LogLevel::Emergency));
        $this->assertSame('warning', $formatter->formatLevel(LogLevel::Warning));
        $this->assertSame('debug', $formatter->formatLevel(LogLevel::Debug));
    }

    #[Test]
    public function it_formats_level_in_uppercase_when_configured(): void
    {
        $formatter = new LevelFormatter(uppercase: true);

        $this->assertSame('EMERGENCY', $formatter->formatLevel(LogLevel::Emergency));
        $this->assertSame('WARNING', $formatter->formatLevel(LogLevel::Warning));
        $this->assertSame('DEBUG', $formatter->formatLevel(LogLevel::Debug));
    }

    #[Test]
    public function it_always_uses_the_full_level_name(): void
    {
        $formatter = new LevelFormatter();

        foreach (LogLevel::cases() as $level) {
            $this->assertSame($level->getFullName(), $formatter->formatLevel($level));
        }
    }
}
