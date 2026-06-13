<?php

declare(strict_types=1);

namespace Ordinary\Config;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Config source backed by a JSON file.
 *
 * A thin wrapper around JsonConfig that reads the file at construction time.
 * Flatten and separator options are forwarded directly to JsonConfig.
 *
 * @throws InvalidArgumentException when $path does not point to a readable file,
 *                                  or when the file does not contain a JSON object.
 * @throws JsonException when the file contents are not valid JSON.
 * @throws RuntimeException when the file cannot be read.
 */
final readonly class JsonFileConfig implements Config
{
    private JsonConfig $inner;

    public function __construct(
        string $path,
        bool $flatten = false,
        string $separator = '.',
    ) {
        if (!\is_file($path)) {
            throw new InvalidArgumentException('Config file not found: ' . $path);
        }

        $json = \file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('Failed to read config file: ' . $path);
        }

        $this->inner = new JsonConfig($json, $flatten, $separator);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }
}
