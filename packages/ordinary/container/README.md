# ordinary/container

A modern PHP 8.5 dependency injection container featuring native lazy objects (PHP 8.4+), zero-config autowiring, callable injection, contextual bindings, service tagging, scoped lifetimes, and full PSR-11 compliance.

---

## Installation

```bash
composer require ordinary/container
```

## Quick Start

```php
use Ordinary\Container\ContainerBuilder;

$container = (new ContainerBuilder())
    ->singleton(Database::class, fn($c) => new Database($c->get(Config::class)))
    ->singleton(Config::class, fn($c) => new Config('/etc/app.ini'))
    ->build();

$db = $container->get(Database::class);
```

Any instantiable class with type-hinted constructor parameters can also be resolved without registration — see [Zero-config autowiring](#zero-config-autowiring).

---

## Binding Services

### Singleton — one shared instance

```php
$builder->singleton(Cache::class, fn($c) => new RedisCache($c->get(Redis::class)));
```

### Transient — new instance on every call

```php
$builder->transient(QueryBuilder::class, fn($c) => new QueryBuilder($c->get(Database::class)));
```

### Scoped — one instance per named scope

```php
$builder->scoped(RequestContext::class, fn($c) => new RequestContext());

// Create an isolated scope (e.g. per HTTP request in a long-running process)
$requestContainer = $container->scope('request-1');
$ctx = $requestContainer->get(RequestContext::class); // fresh instance for this scope
$ctx2 = $requestContainer->get(RequestContext::class); // same instance within the scope
```

Scoped services throw `ContainerException` when resolved outside a scope. Always call `scope()` first.

### Lazy Singleton — deferred via PHP 8.4 native lazy proxies

```php
$builder->lazySingleton(ExpensiveService::class, fn($c) => new ExpensiveService());
// Proxy returned immediately; real instance created only on first property or method access.
// Falls back to eager resolution for final classes and non-class-string ids.
```

### Choosing a scope

| Scope | Use when |
|---|---|
| `Singleton` | Stateless services, shared connections, caches — created once and reused for the lifetime of the container. |
| `Transient` | Stateful value objects, builders, or anything that must not share state between callers. |
| `Scoped` | Services that should be shared within a logical unit of work (e.g. one HTTP request in FrankenPHP or Swoole) but isolated between units. Requires `scope()`. |

### Aliases

```php
$builder->singleton(RedisCache::class, fn($c) => new RedisCache());
$builder->alias(CacheInterface::class, RedisCache::class);

$cache = $container->get(CacheInterface::class); // resolves to RedisCache
```

---

## Autowiring

Register a class without writing a factory — the container resolves constructor parameters by type:

```php
$builder->autowire(Mailer::class);                          // singleton by default
$builder->autowire(Logger::class, Scope::Transient);        // explicit scope
$builder->autowire(Redis::class, overrides: ['host' => 'localhost', 'port' => 6379]);
```

### Zero-config autowiring

Autowiring is enabled by default. Any instantiable class can be resolved without explicit registration:

```php
// No registration needed — resolved automatically if the constructor can be wired
$svc = $container->get(SimpleService::class);
```

Auto-resolved classes are cached as singletons and shared across child scopes. To require explicit registration for every service:

```php
$builder->useAutowiring(false);
```

### `#[Inject]` — override a specific constructor parameter

```php
use Ordinary\Container\Attribute\Inject;

class ReportMailer
{
    public function __construct(
        #[Inject(S3Storage::class)] private StorageInterface $storage,
    ) {}
}

$builder->autowire(ReportMailer::class);
```

### `#[Singleton]` / `#[Transient]` — attribute-driven scope

```php
use Ordinary\Container\Attribute\Singleton;

#[Singleton]
class SessionManager { ... }

$builder->autowireFromAttribute(SessionManager::class);
// Defaults to Scope::Singleton when neither attribute is present.
```

### Autowiring limitations

- **Union and intersection types** — parameters typed `CacheInterface|NullCache` are skipped. Supply them via `$overrides` or `#[Inject]`.
- **Built-in type parameters** — `string`, `int`, `array`, etc. require either a default value, an explicit `$overrides` entry, or `#[Inject]`. The container cannot resolve scalar values by type.
- **Nullable built-in with no default** — resolved to `null`.

---

## Calling Callables with Injection

Invoke any PHP callable with parameters resolved from the container:

```php
$result = $container->call(static function (Mailer $mailer, Logger $logger): string {
    return $mailer->send('Hello');
});
```

Pass named overrides for parameters that cannot be resolved by type:

```php
$container->call([$handler, 'process'], parameters: ['timeout' => 30]);
```

The `#[Inject]` attribute is honoured on closure and function parameters:

```php
$container->call(
    static function (#[Inject(S3Storage::class)] StorageInterface $storage): void {
        $storage->store('payload');
    },
);
```

Any PHP callable is accepted: closures, named functions, `[$object, 'method']` arrays, `'Class::method'` strings, and invokable objects.

---

## Tagging

```php
$builder->singleton(JsonSerializer::class, fn($c) => new JsonSerializer(), 'serializer');
$builder->singleton(MsgpackSerializer::class, fn($c) => new MsgpackSerializer(), 'serializer');

foreach ($container->tagged('serializer') as $serializer) {
    // ...
}
```

Or tag after the fact:

```php
$builder->singleton(JsonSerializer::class, fn($c) => new JsonSerializer());
$builder->tag(JsonSerializer::class, 'serializer');
```

---

## Contextual Bindings

Inject a different implementation depending on which class is requesting it:

```php
$builder->singleton(S3Storage::class, fn($c) => new S3Storage());
$builder->singleton(LocalStorage::class, fn($c) => new LocalStorage());
$builder->alias(StorageInterface::class, LocalStorage::class); // default

$builder->when(ReportController::class)
    ->needs(StorageInterface::class)
    ->give(S3Storage::class);
```

Works for both autowired and manually-factored services.

---

## Service Providers

Group related bindings into a reusable provider:

```php
use Ordinary\Container\ServiceProviderInterface;
use Ordinary\Container\ContainerBuilder;

class StorageServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(S3Storage::class, fn($c) => new S3Storage());
        $builder->alias(StorageInterface::class, S3Storage::class);
    }
}

$builder->provider(new StorageServiceProvider());
```

---

## Circular Dependency Detection

The container throws `CircularDependencyException` when a dependency cycle is detected, including through auto-wired classes:

```php
// A depends on B, B depends on A → throws CircularDependencyException with full chain
$container->get(ServiceA::class);
```

The exception exposes the full chain via `$e->chain()` for diagnostics.

---

## PSR-11 Compliance

`Container` implements `Psr\Container\ContainerInterface`. It is compatible with any framework or library that accepts a PSR-11 container.

```php
use Psr\Container\ContainerInterface;

function bootstrap(ContainerInterface $c): void
{
    $app = $c->get(Application::class);
    $app->run();
}

bootstrap($container);
```

---

## License

MIT
