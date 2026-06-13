# ordinary/config

Flat key/value configuration management with layered and pipeline resolution.

Config sets are flat — every key is a string and every value is `mixed`. Nested
data structures are a matter of convention (e.g. `"db.host"`) or of JSON
flattening at load time. Sources are composable: combine them in a `LayeredConfig`
for priority-based resolution, or in a `PipelineConfig` for per-key resolver
chains that can transform, short-circuit, or delegate to underlying sources.

## Installation

```bash
composer require ordinary/config
```

## Core concepts

### The `Config` interface

All sources implement the same contract:

```php
interface Config
{
    public function has(string $key): bool;
    public function get(string $key, mixed $default = null): mixed;
}
```

A key that exists with a `null` value satisfies `has()`. A missing key does not.

### Layered resolution

`LayeredConfig` accepts any number of `Config` sources. Sources are checked in
registration order; the **first** source that owns the key wins. Register
high-priority sources (overrides, environment) before low-priority ones
(defaults, files). No write-back occurs — sources remain independent.

```php
use Ordinary\Config\LayeredConfig;
use Ordinary\Config\RuntimeConfig;
use Ordinary\Config\JsonFileConfig;
use Ordinary\Config\CachingConfig;

$config = new LayeredConfig(
    new RuntimeConfig(['debug' => true]),                        // highest priority
    new CachingConfig(new JsonFileConfig('/etc/app/config.json')), // cached file
    new RuntimeConfig(['debug' => false, 'timeout' => 30]),      // defaults
);

$config->get('debug');   // true  — from first source
$config->get('timeout'); // 30    — falls through to defaults
```

### Pipeline resolution with per-key handlers

`PipelineConfig` wraps any number of `Config` sources and allows you to register
per-key resolver chains via `define()`. Each handler in the chain receives the key
and a `$next` callable. Calling `$next($key)` passes control to the next handler;
when the chain is exhausted `$next` consults the sources in registration order.
Returning without calling `$next` short-circuits further resolution.

Keys with no registered handlers fall through to sources directly, identical to
`LayeredConfig` behaviour.

```php
use Ordinary\Config\PipelineConfig;
use Ordinary\Config\RuntimeConfig;

$source = new RuntimeConfig(['db.host' => 'localhost']);
$config = new PipelineConfig($source);

// Short-circuit — ignore all sources
$config->define('app.env', fn(string $k, \Closure $next) => 'production');

// Transform — upper-case whatever the source holds
$config->define('db.host', function (string $k, \Closure $next): mixed {
    $val = $next($k);
    return \is_string($val) ? \strtoupper($val) : $val;
});

$config->get('app.env'); // 'production'  — source never consulted
$config->get('db.host'); // 'LOCALHOST'   — source value transformed
$config->get('db.port'); // null          — falls through to source, missing
```

#### Memoising cache layer

Because handlers close over arbitrary state, a handler can act as an in-process
cache for its key — no extra class required:

```php
$config = new PipelineConfig($expensiveSource);

$cache = [];
$config->define(
    'db.dsn',
    static function (string $k, \Closure $next) use (&$cache): mixed {
        return $cache[$k] ??= $next($k);
    },
);

$config->get('db.dsn'); // resolved from source, stored in $cache
$config->get('db.dsn'); // served from $cache — source not consulted again
```

#### Multi-level cache chain

String a chain of handlers to model layered caches in front of a source of truth:

```php
use Ordinary\Config\PipelineConfig;
use Ordinary\Config\RuntimeConfig;

$runtime = [];   // hot in-process cache
$redis   = [];   // simulated Redis cache (use your real adapter here)
$db      = new RuntimeConfig(['api.key' => 'secret']); // source of truth

$config = new PipelineConfig($db);

$config->define(
    'api.key',
    // Layer 1 — runtime
    static function (string $k, \Closure $next) use (&$runtime): mixed {
        return $runtime[$k] ??= $next($k);
    },
    // Layer 2 — Redis
    static function (string $k, \Closure $next) use (&$redis): mixed {
        return $redis[$k] ??= $next($k);
    },
);

$config->get('api.key'); // misses runtime → misses Redis → hits DB; warms both caches
$config->get('api.key'); // served from runtime; Redis and DB not touched
```

#### Chain ordering and fluent API

`define()` accepts multiple handlers in a single call (run left-to-right) and
returns `static` for fluent chaining. Calling `define()` for the same key a
second time **replaces** the previous chain entirely.

```php
$config = (new PipelineConfig($source))
    ->define('a', fn(string $k, \Closure $next) => 'override-a')
    ->define('b', fn(string $k, \Closure $next) => $next($k));
```

## Sources

### `RuntimeConfig`

Mutable in-memory store. Useful for overrides, environment injection, and test
fixtures. Implements `StackLayer`, so it participates in `StackConfig` write-back
automatically.

```php
$config = new RuntimeConfig(['key' => 'value']);

$config->set('another', 42);
$config->remove('key');

$config->has('another'); // true
$config->get('another'); // 42
```

### `JsonConfig`

Config from a JSON string. By default only root-level keys are visible. Pass
`flatten: true` to recursively expand nested objects into dot-separated keys.
Indexed JSON arrays are never flattened — they remain as their decoded value.

```php
use Ordinary\Config\JsonConfig;

// Root-level only (default)
$config = new JsonConfig('{"host": "localhost", "port": 3306}');
$config->get('host'); // "localhost"

// Flatten nested objects
$config = new JsonConfig(
    '{"db": {"host": "localhost", "port": 3306}, "tags": ["php"]}',
    flatten: true,
);
$config->get('db.host'); // "localhost"
$config->get('db.port'); // 3306
$config->get('tags');    // ["php"]  — indexed arrays are not flattened

// Custom separator
$config = new JsonConfig('{"db": {"host": "localhost"}}', flatten: true, separator: '/');
$config->get('db/host'); // "localhost"
```

### `JsonFileConfig`

Reads a JSON file at construction time and delegates to `JsonConfig`. Accepts
the same `flatten` and `separator` options.

```php
use Ordinary\Config\JsonFileConfig;

$config = new JsonFileConfig('/path/to/config.json');
$config = new JsonFileConfig('/path/to/config.json', flatten: true);
$config = new JsonFileConfig('/path/to/config.json', flatten: true, separator: '/');
```

### `CachingConfig`

Decorator that wraps any `Config` source with an in-memory read-through cache.
Each key is resolved from the inner source at most once — both resolved values
(including `null`) and confirmed absences are cached.

```php
use Ordinary\Config\CachingConfig;
use Ordinary\Config\JsonFileConfig;

$config = new CachingConfig(new JsonFileConfig('/path/to/config.json'));

// File is read only once; all subsequent accesses hit the cache.
$config->get('key');
$config->get('key');
```

## Composition example

```php
use Ordinary\Config\CachingConfig;
use Ordinary\Config\JsonFileConfig;
use Ordinary\Config\LayeredConfig;
use Ordinary\Config\RuntimeConfig;

$config = new LayeredConfig(
    // Runtime overrides — highest priority
    new RuntimeConfig([
        'app.env' => $_SERVER['APP_ENV'] ?? 'production',
    ]),
    // Cached file — read once, served many times
    new CachingConfig(new JsonFileConfig('/etc/app/config.json', flatten: true)),
    // Compiled-in defaults — lowest priority
    new RuntimeConfig([
        'app.env'     => 'production',
        'app.debug'   => false,
        'db.port'     => 3306,
        'cache.ttl'   => 3600,
    ]),
);
```
