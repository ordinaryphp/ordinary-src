<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use Ordinary\Config\RuntimeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimeConfig::class)]
final class RuntimeConfigTest extends TestCase
{
    #[Test]
    public function it_starts_empty_by_default(): void
    {
        $config = new RuntimeConfig();

        $this->assertFalse($config->has('key'));
        $this->assertNull($config->get('key'));
    }

    #[Test]
    public function it_initialises_from_array(): void
    {
        $config = new RuntimeConfig(['a' => 1, 'b' => 'two']);

        $this->assertTrue($config->has('a'));
        $this->assertSame(1, $config->get('a'));
        $this->assertSame('two', $config->get('b'));
    }

    #[Test]
    public function it_returns_provided_default_for_absent_key(): void
    {
        $config = new RuntimeConfig();

        $this->assertSame('default', $config->get('missing', 'default'));
    }

    #[Test]
    public function it_sets_and_retrieves_a_value(): void
    {
        $config = new RuntimeConfig();
        $config->set('foo', 'bar');

        $this->assertTrue($config->has('foo'));
        $this->assertSame('bar', $config->get('foo'));
    }

    #[Test]
    public function it_overwrites_an_existing_key(): void
    {
        $config = new RuntimeConfig(['key' => 'original']);
        $config->set('key', 'updated');

        $this->assertSame('updated', $config->get('key'));
    }

    #[Test]
    public function it_stores_null_values_as_present(): void
    {
        $config = new RuntimeConfig();
        $config->set('nullable', null);

        $this->assertTrue($config->has('nullable'));
        $this->assertNull($config->get('nullable', 'fallback'));
    }

    #[Test]
    public function it_removes_a_key(): void
    {
        $config = new RuntimeConfig(['key' => 'value']);
        $config->remove('key');

        $this->assertFalse($config->has('key'));
        $this->assertNull($config->get('key'));
    }

    #[Test]
    public function it_tolerates_removing_an_absent_key(): void
    {
        $config = new RuntimeConfig();

        $config->remove('never-existed');

        $this->assertFalse($config->has('never-existed'));
    }

}
