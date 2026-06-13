<?php

declare(strict_types=1);

namespace Ordinary\Config;

use InvalidArgumentException;
use JsonException;

/**
 * Config source backed by a JSON payload.
 *
 * By default only root-level keys are visible. Pass $flatten = true to
 * recursively expand nested JSON objects into dot-separated top-level keys.
 * Indexed JSON arrays are never flattened and remain as their decoded value.
 *
 * @throws JsonException when $json is syntactically invalid.
 * @throws InvalidArgumentException when $json decodes to a scalar rather than an array or object.
 */
final readonly class JsonConfig implements Config
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(
        string $json,
        bool $flatten = false,
        string $separator = '.',
    ) {
        $decoded = \json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('JSON payload must decode to a JSON object, not a scalar.');
        }

        $this->data = $flatten
            ? $this->flatten($decoded, $separator)
            : $this->toStringKeys($decoded);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function toStringKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Recursively flattens associative arrays into a single level using $separator.
     * Indexed arrays are left as-is and stored as leaf values.
     *
     * @param array<int|string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $separator, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $rawKey => $value) {
            $key = $prefix !== '' ? $prefix . $separator . $rawKey : (string) $rawKey;

            if (\is_array($value) && !\array_is_list($value)) {
                foreach ($this->flatten($value, $separator, $key) as $flatKey => $flatValue) {
                    $result[$flatKey] = $flatValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
