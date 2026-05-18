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

    /** @return array<string, array{string, LogLevel}> */
    public static function full_name_provider(): array
    {
        return [
            'emergency' => ['emergency', LogLevel::Emergency],
            'alert' => ['alert', LogLevel::Alert],
            'critical' => ['critical', LogLevel::Critical],
            'error' => ['error', LogLevel::Error],
            'warning' => ['warning', LogLevel::Warning],
            'notice' => ['notice', LogLevel::Notice],
            'info' => ['info', LogLevel::Info],
            'debug' => ['debug', LogLevel::Debug],
        ];
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

    /** @return array<string, array{string, LogLevel}> */
    public static function prefix_provider(): array
    {
        return [
            'emer' => ['emer', LogLevel::Emergency],
            'emerg' => ['emerg', LogLevel::Emergency],
            'aler' => ['aler', LogLevel::Alert],
            'alert' => ['alert', LogLevel::Alert],
            'crit' => ['crit', LogLevel::Critical],
            'criti' => ['criti', LogLevel::Critical],
            'err' => ['err', LogLevel::Error],
            'erro' => ['erro', LogLevel::Error],
            'warn' => ['warn', LogLevel::Warning],
            'warni' => ['warni', LogLevel::Warning],
            'noti' => ['noti', LogLevel::Notice],
            'notic' => ['notic', LogLevel::Notice],
            'info' => ['info', LogLevel::Info],
            'deb' => ['deb', LogLevel::Debug],
            'debu' => ['debu', LogLevel::Debug],
        ];
    }

    #[Test]
    #[DataProvider('prefix_provider')]
    public function it_resolves_from_valid_prefix(string $input, LogLevel $expected): void
    {
        $this->assertSame($expected, LogLevel::fromString($input));
    }

    /** @return array<string, array{string}> */
    public static function invalid_input_provider(): array
    {
        return [
            'empty' => [''],
            'too short for any prefix' => ['e'],
            'below minimum prefix for error' => ['er'],
            'unknown level' => ['verbose'],
            'partial below min prefix' => ['war'],
            'partial below min prefix 2' => ['no'],
        ];
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
