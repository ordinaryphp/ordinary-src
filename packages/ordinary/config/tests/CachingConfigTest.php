<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use Ordinary\Config\CachingConfig;
use Ordinary\Config\RuntimeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachingConfig::class)]
final class CachingConfigTest extends TestCase
{
    #[Test]
    public function it_resolves_from_inner_config(): void
    {
        $caching = new CachingConfig(new RuntimeConfig(['foo' => 'bar']));

        $this->assertTrue($caching->has('foo'));
        $this->assertSame('bar', $caching->get('foo'));
    }

    #[Test]
    public function it_returns_default_for_absent_key(): void
    {
        $caching = new CachingConfig(new RuntimeConfig([]));

        $this->assertFalse($caching->has('missing'));
        $this->assertSame('default', $caching->get('missing', 'default'));
    }

    #[Test]
    public function it_serves_cached_value_after_inner_changes(): void
    {
        $inner = new RuntimeConfig(['key' => 'original']);
        $caching = new CachingConfig($inner);

        $this->assertSame('original', $caching->get('key'));

        $inner->set('key', 'updated');

        $this->assertSame('original', $caching->get('key'));
    }

    #[Test]
    public function it_caches_null_values_as_present(): void
    {
        $inner = new RuntimeConfig(['nullable' => null]);
        $caching = new CachingConfig($inner);

        $this->assertTrue($caching->has('nullable'));
        $this->assertNull($caching->get('nullable', 'fallback'));

        $inner->remove('nullable');

        $this->assertTrue($caching->has('nullable'));
        $this->assertNull($caching->get('nullable', 'fallback'));
    }

    #[Test]
    public function it_caches_key_absence_so_later_inner_additions_are_invisible(): void
    {
        $inner = new RuntimeConfig([]);
        $caching = new CachingConfig($inner);

        $this->assertFalse($caching->has('key'));
        $this->assertNull($caching->get('key'));

        $inner->set('key', 'added-later');

        $this->assertFalse($caching->has('key'));
        $this->assertNull($caching->get('key'));
    }

    #[Test]
    public function it_caches_absence_detected_via_get_before_has(): void
    {
        $inner = new RuntimeConfig([]);
        $caching = new CachingConfig($inner);

        $this->assertNull($caching->get('key'));

        $inner->set('key', 'added-later');

        $this->assertNull($caching->get('key'));
        $this->assertFalse($caching->has('key'));
    }

    #[Test]
    public function it_handles_multiple_distinct_keys_independently(): void
    {
        $inner = new RuntimeConfig(['a' => 1, 'b' => 2]);
        $caching = new CachingConfig($inner);

        $this->assertSame(1, $caching->get('a'));
        $this->assertSame(2, $caching->get('b'));
        $this->assertNull($caching->get('c'));

        $inner->set('a', 99);
        $inner->set('c', 3);

        $this->assertSame(1, $caching->get('a'));
        $this->assertSame(2, $caching->get('b'));
        $this->assertNull($caching->get('c'));
    }
}
