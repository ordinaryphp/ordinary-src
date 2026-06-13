<?php

declare(strict_types=1);

namespace Ordinary\Config\Tests;

use InvalidArgumentException;
use Ordinary\Config\JsonFileConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFileConfig::class)]
final class JsonFileConfigTest extends TestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'ordinary_config_test_');

        if ($path === false) {
            $this->fail('Could not create temp file.');
        }

        $this->tempFile = $path;
    }

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && \file_exists($this->tempFile)) {
            \unlink($this->tempFile);
        }
    }

    #[Test]
    public function it_reads_and_exposes_json_file_keys(): void
    {
        \file_put_contents($this->tempFile, '{"host": "localhost", "port": 3306}');

        $config = new JsonFileConfig($this->tempFile);

        $this->assertTrue($config->has('host'));
        $this->assertSame('localhost', $config->get('host'));
        $this->assertSame(3306, $config->get('port'));
    }

    #[Test]
    public function it_returns_default_for_absent_key(): void
    {
        \file_put_contents($this->tempFile, '{"a": 1}');

        $config = new JsonFileConfig($this->tempFile);

        $this->assertFalse($config->has('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    #[Test]
    public function it_throws_when_file_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file not found');

        new JsonFileConfig('/no/such/file.json');
    }

    #[Test]
    public function it_forwards_flatten_option(): void
    {
        \file_put_contents($this->tempFile, '{"db": {"host": "localhost"}}');

        $config = new JsonFileConfig($this->tempFile, flatten: true);

        $this->assertTrue($config->has('db.host'));
        $this->assertSame('localhost', $config->get('db.host'));
    }

    #[Test]
    public function it_forwards_custom_separator(): void
    {
        \file_put_contents($this->tempFile, '{"db": {"host": "localhost"}}');

        $config = new JsonFileConfig($this->tempFile, flatten: true, separator: '/');

        $this->assertTrue($config->has('db/host'));
        $this->assertSame('localhost', $config->get('db/host'));
    }
}
