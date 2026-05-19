<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogLevel::class)]
final class LogLevelTest extends TestCase
{
    #[Test]
    public function it_returns_lowercase_full_name(): void
    {
        $this->assertSame('emergency', LogLevel::Emergency->getFullName());
        $this->assertSame('alert', LogLevel::Alert->getFullName());
        $this->assertSame('critical', LogLevel::Critical->getFullName());
        $this->assertSame('error', LogLevel::Error->getFullName());
        $this->assertSame('warning', LogLevel::Warning->getFullName());
        $this->assertSame('notice', LogLevel::Notice->getFullName());
        $this->assertSame('info', LogLevel::Info->getFullName());
        $this->assertSame('debug', LogLevel::Debug->getFullName());
    }

    #[Test]
    public function it_returns_shortest_identifiable_prefix(): void
    {
        $this->assertSame('emer', LogLevel::Emergency->getShortestIdentifiablePrefix());
        $this->assertSame('aler', LogLevel::Alert->getShortestIdentifiablePrefix());
        $this->assertSame('crit', LogLevel::Critical->getShortestIdentifiablePrefix());
        $this->assertSame('err', LogLevel::Error->getShortestIdentifiablePrefix());
        $this->assertSame('warn', LogLevel::Warning->getShortestIdentifiablePrefix());
        $this->assertSame('noti', LogLevel::Notice->getShortestIdentifiablePrefix());
        $this->assertSame('info', LogLevel::Info->getShortestIdentifiablePrefix());
        $this->assertSame('deb', LogLevel::Debug->getShortestIdentifiablePrefix());
    }

    /** @return \Iterator<string, array{string, LogLevel}> */
    public static function full_name_provider(): \Iterator
    {
        yield 'emergency' => ['emergency', LogLevel::Emergency];
        yield 'alert' => ['alert', LogLevel::Alert];
        yield 'critical' => ['critical', LogLevel::Critical];
        yield 'error' => ['error', LogLevel::Error];
        yield 'warning' => ['warning', LogLevel::Warning];
        yield 'notice' => ['notice', LogLevel::Notice];
        yield 'info' => ['info', LogLevel::Info];
        yield 'debug' => ['debug', LogLevel::Debug];
    }

    #[Test]
    #[DataProvider('full_name_provider')]
    public function it_resolves_from_full_name(string $input, LogLevel $expected): void
    {
        $this->assertSame($expected, LogLevel::fromString($input));
    }

    #[Test]
    public function it_resolves_from_full_name_case_insensitively(): void
    {
        $this->assertSame(LogLevel::Warning, LogLevel::fromString('WARNING'));
        $this->assertSame(LogLevel::Error, LogLevel::fromString('Error'));
        $this->assertSame(LogLevel::Debug, LogLevel::fromString('  DEBUG  '));
    }

    /** @return \Iterator<string, array{string, LogLevel}> */
    public static function prefix_provider(): \Iterator
    {
        yield 'emer' => ['emer', LogLevel::Emergency];
        yield 'emerg' => ['emerg', LogLevel::Emergency];
        yield 'aler' => ['aler', LogLevel::Alert];
        yield 'alert' => ['alert', LogLevel::Alert];
        yield 'crit' => ['crit', LogLevel::Critical];
        yield 'criti' => ['criti', LogLevel::Critical];
        yield 'err' => ['err', LogLevel::Error];
        yield 'erro' => ['erro', LogLevel::Error];
        yield 'warn' => ['warn', LogLevel::Warning];
        yield 'warni' => ['warni', LogLevel::Warning];
        yield 'noti' => ['noti', LogLevel::Notice];
        yield 'notic' => ['notic', LogLevel::Notice];
        yield 'info' => ['info', LogLevel::Info];
        yield 'deb' => ['deb', LogLevel::Debug];
        yield 'debu' => ['debu', LogLevel::Debug];
    }

    #[Test]
    #[DataProvider('prefix_provider')]
    public function it_resolves_from_valid_prefix(string $input, LogLevel $expected): void
    {
        $this->assertSame($expected, LogLevel::fromString($input));
    }

    /** @return \Iterator<string, array{string}> */
    public static function invalid_input_provider(): \Iterator
    {
        yield 'empty' => [''];
        yield 'too short for any prefix' => ['e'];
        yield 'below minimum prefix for error' => ['er'];
        yield 'unknown level' => ['verbose'];
        yield 'partial below min prefix' => ['war'];
        yield 'partial below min prefix 2' => ['no'];
    }

    #[Test]
    #[DataProvider('invalid_input_provider')]
    public function it_throws_for_invalid_string(string $input): void
    {
        $this->expectException(\ValueError::class);

        LogLevel::fromString($input);
    }

    #[Test]
    public function it_compares_severity_correctly(): void
    {
        $this->assertTrue(LogLevel::Emergency->isHigherThan(LogLevel::Debug));
        $this->assertTrue(LogLevel::Debug->isLowerThan(LogLevel::Info));
        $this->assertFalse(LogLevel::Info->isHigherThan(LogLevel::Warning));
        $this->assertFalse(LogLevel::Error->isLowerThan(LogLevel::Error));
    }

    #[Test]
    public function it_identifies_same_level(): void
    {
        $this->assertTrue(LogLevel::Warning->isSameAs(LogLevel::Warning));
        $this->assertFalse(LogLevel::Warning->isSameAs(LogLevel::Error));
    }

    #[Test]
    public function it_returns_correct_compare_to_sign(): void
    {
        $this->assertLessThan(0, LogLevel::Debug->compareTo(LogLevel::Info));
        $this->assertGreaterThan(0, LogLevel::Emergency->compareTo(LogLevel::Alert));
        $this->assertSame(0, LogLevel::Notice->compareTo(LogLevel::Notice));
    }

    #[Test]
    public function severity_order_matches_psr_3(): void
    {
        $ordered = [
            LogLevel::Debug,
            LogLevel::Info,
            LogLevel::Notice,
            LogLevel::Warning,
            LogLevel::Error,
            LogLevel::Critical,
            LogLevel::Alert,
            LogLevel::Emergency,
        ];

        for ($i = 0; $i < \count($ordered) - 1; $i++) {
            $this->assertTrue(
                $ordered[$i]->isLowerThan($ordered[$i + 1]),
                \sprintf('%s should be lower than %s', $ordered[$i]->name, $ordered[$i + 1]->name),
            );
        }
    }
}
