<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use InvalidArgumentException;
use JsonException;
use Ordinary\Config\JsonConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonConfig::class)]
final class JsonConfigTest extends TestCase
{
    #[Test]
    public function it_exposes_root_level_keys(): void
    {
        $config = new JsonConfig('{"host": "localhost", "port": 3306}');

        $this->assertTrue($config->has('host'));
        $this->assertSame('localhost', $config->get('host'));
        $this->assertSame(3306, $config->get('port'));
    }

    #[Test]
    public function it_returns_null_default_for_absent_key(): void
    {
        $config = new JsonConfig('{"a": 1}');

        $this->assertFalse($config->has('missing'));
        $this->assertNull($config->get('missing'));
    }

    #[Test]
    public function it_returns_provided_default_for_absent_key(): void
    {
        $config = new JsonConfig('{"a": 1}');

        $this->assertSame('default', $config->get('missing', 'default'));
    }

    #[Test]
    public function it_stores_null_json_values_as_present(): void
    {
        $config = new JsonConfig('{"key": null}');

        $this->assertTrue($config->has('key'));
        $this->assertNull($config->get('key', 'fallback'));
    }

    #[Test]
    public function it_does_not_expose_nested_keys_without_flatten(): void
    {
        $config = new JsonConfig('{"db": {"host": "localhost"}}');

        $this->assertTrue($config->has('db'));
        $this->assertFalse($config->has('db.host'));
    }

    #[Test]
    public function it_flattens_nested_objects_with_dot_separator_by_default(): void
    {
        $config = new JsonConfig(
            '{"db": {"host": "localhost", "port": 3306}}',
            flatten: true,
        );

        $this->assertFalse($config->has('db'));
        $this->assertTrue($config->has('db.host'));
        $this->assertSame('localhost', $config->get('db.host'));
        $this->assertSame(3306, $config->get('db.port'));
    }

    #[Test]
    public function it_flattens_deeply_nested_objects(): void
    {
        $config = new JsonConfig(
            '{"a": {"b": {"c": "deep"}}}',
            flatten: true,
        );

        $this->assertSame('deep', $config->get('a.b.c'));
    }

    #[Test]
    public function it_flattens_with_a_custom_separator(): void
    {
        $config = new JsonConfig(
            '{"db": {"host": "localhost"}}',
            flatten: true,
            separator: '_',
        );

        $this->assertTrue($config->has('db_host'));
        $this->assertSame('localhost', $config->get('db_host'));
    }

    #[Test]
    public function it_does_not_flatten_indexed_arrays(): void
    {
        $config = new JsonConfig(
            '{"tags": ["php", "config"]}',
            flatten: true,
        );

        $this->assertTrue($config->has('tags'));
        $this->assertSame(['php', 'config'], $config->get('tags'));
        $this->assertFalse($config->has('tags.0'));
    }

    #[Test]
    public function it_mixes_flat_and_nested_keys(): void
    {
        $config = new JsonConfig(
            '{"name": "app", "db": {"host": "localhost"}, "debug": true}',
            flatten: true,
        );

        $this->assertSame('app', $config->get('name'));
        $this->assertSame('localhost', $config->get('db.host'));
        $this->assertTrue($config->get('debug'));
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(JsonException::class);

        new JsonConfig('{invalid json}');
    }

    #[Test]
    public function it_throws_when_json_is_a_scalar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JsonConfig('"just a string"');
    }

    /**
     * @return \Iterator<string, array{string, string, mixed}>
     */
    public static function primitiveValueProvider(): \Iterator
    {
        yield 'string' => ['{"key": "value"}', 'key', 'value'];
        yield 'integer' => ['{"key": 42}', 'key', 42];
        yield 'float' => ['{"key": 3.14}', 'key', 3.14];
        yield 'bool true' => ['{"key": true}', 'key', true];
        yield 'bool false' => ['{"key": false}', 'key', false];
    }

    #[Test]
    #[DataProvider('primitiveValueProvider')]
    public function it_handles_primitive_json_types(string $json, string $key, mixed $expected): void
    {
        $config = new JsonConfig($json);

        $this->assertSame($expected, $config->get($key));
    }
}
