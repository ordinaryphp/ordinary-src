<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use Ordinary\Config\LayeredConfig;
use Ordinary\Config\RuntimeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LayeredConfig::class)]
final class LayeredConfigTest extends TestCase
{
    #[Test]
    public function it_returns_default_when_no_sources_provided(): void
    {
        $config = new LayeredConfig();

        $this->assertNull($config->get('key'));
        $this->assertSame('fallback', $config->get('key', 'fallback'));
        $this->assertFalse($config->has('key'));
    }

    #[Test]
    public function it_resolves_from_the_only_source(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig(['foo' => 'bar']),
        );

        $this->assertTrue($config->has('foo'));
        $this->assertSame('bar', $config->get('foo'));
    }

    #[Test]
    public function it_returns_default_when_key_absent_from_all_sources(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig(['a' => 1]),
            new RuntimeConfig(['b' => 2]),
        );

        $this->assertFalse($config->has('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    #[Test]
    public function it_resolves_from_first_source_in_registration_order(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig(['key' => 'first']),
            new RuntimeConfig(['key' => 'second']),
        );

        $this->assertSame('first', $config->get('key'));
    }

    #[Test]
    public function it_falls_through_to_later_sources_when_earlier_lacks_key(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig(['a' => 1]),
            new RuntimeConfig(['b' => 2]),
        );

        $this->assertSame(1, $config->get('a'));
        $this->assertSame(2, $config->get('b'));
    }

    #[Test]
    public function it_has_returns_true_when_any_source_owns_the_key(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig([]),
            new RuntimeConfig(['deep' => 'value']),
        );

        $this->assertTrue($config->has('deep'));
    }

    #[Test]
    public function it_recognises_null_as_a_present_value(): void
    {
        $config = new LayeredConfig(
            new RuntimeConfig(['nullable' => null]),
            new RuntimeConfig(['nullable' => 'should-not-reach']),
        );

        $this->assertTrue($config->has('nullable'));
        $this->assertNull($config->get('nullable', 'fallback'));
    }
}
