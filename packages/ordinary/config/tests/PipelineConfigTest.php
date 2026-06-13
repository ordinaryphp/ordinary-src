<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use InvalidArgumentException;
use Ordinary\Config\PipelineConfig;
use Ordinary\Config\RuntimeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PipelineConfig::class)]
final class PipelineConfigTest extends TestCase
{
    #[Test]
    public function it_returns_default_with_no_sources_or_definitions(): void
    {
        $config = new PipelineConfig();

        $this->assertFalse($config->has('key'));
        $this->assertNull($config->get('key'));
        $this->assertSame('fallback', $config->get('key', 'fallback'));
    }

    #[Test]
    public function it_falls_through_to_sources_for_undefined_keys(): void
    {
        $config = new PipelineConfig(new RuntimeConfig(['foo' => 'bar']));

        $this->assertTrue($config->has('foo'));
        $this->assertSame('bar', $config->get('foo'));
    }

    #[Test]
    public function it_resolves_sources_in_registration_order_for_undefined_keys(): void
    {
        $config = new PipelineConfig(
            new RuntimeConfig(['key' => 'first']),
            new RuntimeConfig(['key' => 'second']),
        );

        $this->assertSame('first', $config->get('key'));
    }

    #[Test]
    public function it_short_circuits_when_handler_returns_without_calling_next(): void
    {
        $config = new PipelineConfig(new RuntimeConfig(['key' => 'from-source']));
        $config->define('key', static fn(string $k, \Closure $next): mixed => 'short-circuit');

        $this->assertSame('short-circuit', $config->get('key'));
    }

    #[Test]
    public function it_delegates_to_sources_when_handler_calls_next(): void
    {
        $config = new PipelineConfig(new RuntimeConfig(['key' => 'from-source']));
        $config->define('key', static fn(string $k, \Closure $next): mixed => $next($k));

        $this->assertSame('from-source', $config->get('key'));
    }

    #[Test]
    public function it_transforms_the_value_returned_by_next(): void
    {
        $config = new PipelineConfig(new RuntimeConfig(['key' => 'hello']));
        $config->define('key', static function (string $k, \Closure $next): mixed {
            $val = $next($k);
            return \is_string($val) ? \strtoupper($val) : $val;
        });

        $this->assertSame('HELLO', $config->get('key'));
    }

    #[Test]
    public function it_runs_a_chain_of_handlers_in_order(): void
    {
        /** @var list<string> $called */
        $called = [];

        $config = new PipelineConfig(new RuntimeConfig(['key' => 'source']));
        $config->define(
            'key',
            static function (string $k, \Closure $next) use (&$called): mixed {
                $called[] = 'first';
                return $next($k); // propagates value from second handler
            },
            static function (string $k, \Closure $next) use (&$called): mixed {
                $called[] = 'second';
                return $next($k); // falls through to source
            },
        );

        $this->assertSame('source', $config->get('key'));
        $this->assertSame(['first', 'second'], $called);
    }

    #[Test]
    public function it_acts_as_a_cache_layer_when_handler_memoises_next(): void
    {
        $source = new RuntimeConfig(['key' => 'original']);
        $config = new PipelineConfig($source);

        $cached = [];
        $config->define(
            'key',
            static function (string $k, \Closure $next) use (&$cached): mixed {
                return $cached[$k] ??= $next($k);
            },
        );

        $this->assertSame('original', $config->get('key'));

        $source->set('key', 'changed');

        $this->assertSame('original', $config->get('key')); // served from $cached
    }

    #[Test]
    public function it_passes_the_key_to_next_correctly(): void
    {
        $source = new RuntimeConfig(['key' => 'value']);
        $config = new PipelineConfig($source);

        $observedKey = '';
        $config->define(
            'key',
            static function (string $k, \Closure $next) use (&$observedKey): mixed {
                $observedKey = $k;
                return $next($k);
            },
        );

        $config->get('key');

        $this->assertSame('key', $observedKey);
    }

    #[Test]
    public function it_returns_null_from_next_when_no_source_has_the_key(): void
    {
        $config = new PipelineConfig();
        $config->define('key', static fn(string $k, \Closure $next): mixed => $next($k) ?? 'fallback');

        $this->assertSame('fallback', $config->get('key'));
    }

    #[Test]
    public function it_has_returns_true_for_any_defined_key(): void
    {
        $config = new PipelineConfig(); // no sources
        $config->define('key', static fn(string $k, \Closure $next): mixed => 'value');

        $this->assertTrue($config->has('key'));
    }

    #[Test]
    public function it_has_returns_true_when_source_owns_the_key(): void
    {
        $config = new PipelineConfig(new RuntimeConfig(['key' => 'value']));

        $this->assertTrue($config->has('key'));
    }

    #[Test]
    public function it_has_returns_false_for_unknown_key_with_no_sources(): void
    {
        $config = new PipelineConfig();

        $this->assertFalse($config->has('unknown'));
    }

    #[Test]
    public function it_returns_static_from_define_for_fluent_chaining(): void
    {
        $config = new PipelineConfig()
            ->define('a', static fn(string $k, \Closure $next): mixed => 1)
            ->define('b', static fn(string $k, \Closure $next): mixed => 2);

        $this->assertSame(1, $config->get('a'));
        $this->assertSame(2, $config->get('b'));
    }

    #[Test]
    public function it_replaces_the_chain_when_key_is_redefined(): void
    {
        $config = new PipelineConfig();
        $config->define('key', static fn(string $k, \Closure $next): mixed => 'first');
        $config->define('key', static fn(string $k, \Closure $next): mixed => 'second');

        $this->assertSame('second', $config->get('key'));
    }

    #[Test]
    public function it_throws_when_define_is_called_with_no_resolvers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PipelineConfig()->define('key');
    }
}
