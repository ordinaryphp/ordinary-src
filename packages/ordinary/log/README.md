# ordinary/log

Structured logging library for PHP 8.5+.

## Installation

```bash
composer require ordinary/log
```

## Basic Usage

`Logger` is the ready-to-use implementation. Add one or more drivers and start logging immediately.

```php
use Ordinary\Log\Logger;
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\JsonFormatter;

$logger = new Logger();
$logger->add(new StreamDriver(STDOUT, new JsonFormatter()));

$logger->info('User signed in', ['user_id' => 42]);
$logger->warning('Cache miss on key {key}', ['key' => 'user:42:prefs']);
$logger->error('Payment failed', ['order_id' => 'ORD-999', 'exception' => $e]);
```

Message strings support `{key}` placeholder interpolation using context values.

### Log levels (lowest → highest severity)

`Debug` → `Info` → `Notice` → `Warning` → `Error` → `Critical` → `Alert` → `Emergency`

---

## PSR-3 Compatibility

If any library or framework in your stack type-hints `\Psr\Log\LoggerInterface`, use `Logger::toPsr()` to get a drop-in adapter — one method call, no extra setup:

```php
use Ordinary\Log\Logger;
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\JsonFormatter;

$logger = new Logger();
$logger->add(new StreamDriver(STDOUT, new JsonFormatter()));

// Framework integration — one call produces a PSR-3 adapter
$container->bind(\Psr\Log\LoggerInterface::class, fn() => $logger->toPsr());
```

PSR-3 specifies that Throwables in context must be passed under the key `"exception"`. `LogEntryInterface::RESERVED_EXCEPTION` is also `"exception"`, so no translation is required — the same key works in both the native API and the PSR-3 adapter:

```php
// Native API
$logger->error('Charge failed', ['exception' => $e, 'order_id' => 'ORD-1']);

// PSR-3 API — identical behavior
$logger->toPsr()->error('Charge failed', ['exception' => $e, 'order_id' => 'ORD-1']);
```

If you need to create a `PsrLoggerAdapter` directly (for example, to wrap a custom `LoggerInterface` implementation):

```php
use Ordinary\Log\Psr\PsrLoggerAdapter;

$psrLogger = new PsrLoggerAdapter($myCustomLogger);
```

---

## Formatters

Formatters control how log items are serialized to strings. Two are provided out of the box.

### TextFormatter

Produces a human-readable log line by interpolating `{key}` placeholders in the message string. The keys `{date}` and `{level}` are injected automatically, so they are always available regardless of what context you pass.

Default output (using `StreamDriver`'s defaults — date as ISO-8601, level lowercase):

```
[2024-06-01T12:00:00Z] [error] Something failed order_id=ORD-999
```

Any context keys that are **not** used as `{key}` placeholders in the message are appended as `key=value` pairs.

```php
use Ordinary\Log\TextFormatter;
use Ordinary\Log\DateTimeFormatter;
use Ordinary\Log\LevelFormatter;
use Ordinary\Log\ExceptionFormatter;

// Default — ISO-8601 date, lowercase level, no exception stack traces
$formatter = new TextFormatter();

// Custom date format — daily granularity, uppercase level
$formatter = new TextFormatter(
    dateTimeFormatter: new DateTimeFormatter('Y-m-d', 'America/New_York'),
    levelFormatter: new LevelFormatter(uppercase: true),
);

// With stack traces
$formatter = new TextFormatter(
    exceptionFormatter: new ExceptionFormatter(includeTrace: true),
);
```

Template-driven messages — any `{key}` from context is substituted inline:

```php
// Message: "User john logged in from 192.0.2.1"
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john',
    'ip' => '192.0.2.1',
]);
```

`StreamDriver`, `SyslogDriver`, and `RotatingStreamDriver` all default to `new TextFormatter()`.

### JsonFormatter

Produces structured JSON — one object per log item. Top-level fields: `channel` (if set), `level`, `date`, `message`, `exception` (if a Throwable was attached), `context`.

```php
use Ordinary\Log\JsonFormatter;

// {"level":"error","date":"2024-06-01T12:00:00+00:00","message":"Charge failed","exception":"RuntimeException: ...","context":{"order_id":"ORD-999"}}
$formatter = new JsonFormatter();

// Custom date format
$formatter = new JsonFormatter(
    dateTimeFormatter: new DateTimeFormatter('Y-m-d', 'UTC'),
);
```

Context values are normalized before encoding: `DateTimeInterface` → ISO-8601 string, `\Stringable` → string, `NaN` → `"NaN"`, `INF` → `"Infinity"`, booleans and nulls preserved.

---

## Context Keys

`LogEntryInterface` defines reserved context keys. Understanding which ones you may set versus which are injected helps avoid subtle bugs.

| Key | Constant | Who sets it | Notes |
|---|---|---|---|
| `exception` | `RESERVED_EXCEPTION` | **You** | Pass any `\Throwable`; formatters render it |
| `date` | `RESERVED_DATE` | Formatter | Overwriting silently has no effect |
| `level` | `RESERVED_LEVEL` | Formatter | Same |
| `channel` | `RESERVED_CHANNEL` | Logger (from `$channel` param) | Same |
| `exception.message` | `RESERVED_EXCEPTION_MESSAGE` | Formatter | Same |
| `exception.line` | `RESERVED_EXCEPTION_LINE` | Formatter | Same |
| `exception.code` | `RESERVED_EXCEPTION_CODE` | Formatter | Same |

**`RESERVED_EXCEPTION` is the only reserved key you should set yourself.** All others are injected by the `Logger` or formatters and will be overwritten even if you supply them.

---

## Log Drivers

Drivers implement `LogDriverInterface` with a single `handleLog()` method. They are pure I/O — matching, dispatching, and failure handling are all managed by `Logger`.

### StreamDriver

Writes a formatted line to any writable stream resource.

```php
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\JsonFormatter;

// Write JSON lines to a file
$logger->add(new StreamDriver(
    stream: fopen('/var/log/app.log', 'a'),
    formatter: new JsonFormatter(),
));

// Write errors and above to STDERR
$logger->add(
    new StreamDriver(STDERR, new JsonFormatter()),
    matcher: new IsLevelOrHigher(LogLevel::Error),
);
```

### CloudWatchDriver

Sends log events to AWS CloudWatch Logs. Requires `aws/aws-sdk-php`:

```bash
composer require aws/aws-sdk-php
```

```php
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Ordinary\Log\Driver\CloudWatchDriver;

$logger->add(new CloudWatchDriver(
    client: new CloudWatchLogsClient(['region' => 'us-east-1', 'version' => 'latest']),
    logGroupName: '/my-app/production',
    logStreamName: 'web-01',
    formatter: new JsonFormatter(),
));
```

### BufferingDriver

Accumulates log items in memory and dispatches them in bulk when flushed. Use this to batch writes to high-latency backends like CloudWatch or a database. Call `Logger::flush()` at request end to drain the buffer. The buffer is also drained automatically when the driver is destroyed.

```php
use Ordinary\Log\Driver\BufferingDriver;
use Ordinary\Log\Driver\CloudWatchDriver;

$logger->add(new BufferingDriver(
    inner: new CloudWatchDriver($client, '/app/prod', 'web'),
    flushAfter: 100, // auto-flush at 100 items; omit or set 0 for explicit-only
));

// ... handle request ...

$logger->flush(); // sends all buffered items
```

When the inner driver implements `LogBatchDriverInterface`, `flush()` calls `handleLogBatch()` with the entire buffer in a single operation instead of issuing individual `handleLog()` calls.

### DeduplicatingDriver

Suppresses repeated log items within a sliding time window and re-dispatches a summary when the window closes. Two items are considered duplicates when their fingerprints match; the default fingerprint is `"{level}:{message}"`.

**Behavior:**
- The **first** occurrence of a fingerprint is forwarded immediately as a normal log item.
- **Subsequent** occurrences within the window replace the stored pending item (the latest context is always preserved) and their timestamps are accumulated.
- When the window expires (detected on the next `handleLog` call) **or** when `flush()` is called, any pending item that received at least one duplicate is re-dispatched with two extra context keys added:
  - `dedup_count` — the number of suppressed duplicates (`int`)
  - `dedup_times` — ISO-8601 strings of each suppressed occurrence in `JsonFormatter` output
- Pending items that received **no** duplicates are silently discarded on flush.
- `flush()` clears all pending state — fingerprints are treated as fresh after a flush cycle.
- The driver flushes automatically on destruction, so pending summaries are never lost even without an explicit `flush()` call.

```php
use Ordinary\Log\Driver\DeduplicatingDriver;

$logger->add(new DeduplicatingDriver(
    inner: new StreamDriver(STDERR),
    windowSeconds: 300, // suppress repeats for 5 minutes
));

// Custom fingerprint — deduplicate by event code rather than full message
$logger->add(new DeduplicatingDriver(
    inner: new SlackDriver($webhookUrl),
    windowSeconds: 3600,
    fingerprint: fn(LogEntryInterface $item) => $item->level->name . ':' . ($item->context['event'] ?? $item->message),
));
```

The `$clock` parameter (`Psr\Clock\ClockInterface`, defaults to `UtcClock`) is used for all window tracking. Inject a test double to control time in unit tests.

A typical output sequence for three occurrences of the same log within the window — two dispatches total:

```
[error] Payment failed          ← dispatched immediately (first occurrence)
... 2 more suppressed ...
[error] Payment failed  dedup_count=2  dedup_times=[...]  ← flushed at window close
```

### RotatingStreamDriver

Writes to a date-rotated file. The file path is built by substituting `{date}` in the path pattern with the formatted date of each log item. A new file is opened automatically whenever the date changes:

```php
use Ordinary\Log\Driver\RotatingStreamDriver;
use Ordinary\Log\JsonFormatter;

$logger->add(new RotatingStreamDriver(
    pathPattern: '/var/log/app-{date}.log',
    formatter: new JsonFormatter(),
));
// Produces: /var/log/app-2024-06-01.log, /var/log/app-2024-06-02.log, …
```

Use `$dateFormat` to control rotation granularity — `'Y-m-d'` (default) for daily, `'Y-m-d-H'` for hourly.

### FingersCrossedDriver

Buffers all log items silently until one at or above the activation level arrives, then flushes the entire buffer to the inner driver. This gives full diagnostic context around errors without log noise during normal operation:

```php
use Ordinary\Log\Driver\FingersCrossedDriver;
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\LogLevel;

$logger->add(new FingersCrossedDriver(
    inner: new StreamDriver(fopen('/var/log/app.log', 'a')),
    activationLevel: LogLevel::Error,
));
```

After activation all subsequent items go directly to the inner driver. If the threshold is never reached, `flush()` discards the buffer silently.

Options:
- `$maxBuffer` — cap buffer size (drops oldest item when full); 0 = unlimited.
- `$resetOnFlush` — return to buffering state after each `flush()`, for request-scoped use.

### NullDriver

Silently discards every log item. Useful as a placeholder or to explicitly disable a group during tests:

```php
use Ordinary\Log\Driver\NullDriver;

$logger->add(new NullDriver());
```

### TestDriver

Collects all log items in memory. Use it in tests to assert on what was logged without touching real I/O:

```php
use Ordinary\Log\Driver\TestDriver;
use Ordinary\Log\LogLevel;

$driver = new TestDriver();
$logger->add($driver);

$service->processPayment($order); // triggers $logger->error(...)

$this->assertTrue($driver->hasRecordThatContains('Payment failed', LogLevel::Error));
$this->assertTrue($driver->hasRecord(LogLevel::Error, 'Payment failed'));
$this->assertTrue($driver->hasRecordAtLevel(LogLevel::Error));

$errors = $driver->getRecordsAtLevel(LogLevel::Error); // list<LogEntryInterface>
$driver->reset(); // clear between test cases
```

### Composing decorators

Decorators compose: wrap one inside another and call `Logger::flush()` once — the cascade propagates automatically.

```php
$logger->add(
    new DeduplicatingDriver(
        inner: new BufferingDriver(
            inner: new CloudWatchDriver($client, '/app/prod', 'web'),
            flushAfter: 50,
        ),
        windowSeconds: 60,
    ),
);

$logger->flush(); // DeduplicatingDriver → BufferingDriver → CloudWatchDriver
```

---

## Log Groups

Drivers are organised into named groups. Groups let you apply a shared matcher to multiple drivers without repeating it, and add or remove entire sets of drivers at runtime.

A `default` group is always present with no matcher. Drivers added without specifying a group land there.

```php
// All drivers in this group only receive Warning and above
$logger->addGroup('prod', matcher: new IsLevelOrHigher(LogLevel::Warning));

$logger->add(new CloudWatchDriver($client, '/app/prod', 'web', $formatter), group: 'prod');
$logger->add(new StreamDriver(fopen('/var/log/app.log', 'a'), $formatter), group: 'prod');

// Default group — receives everything
$logger->add(new StreamDriver(STDOUT, $formatter));
```

Per-driver matchers are combined with the group matcher as an AND:

```php
// Group passes Warning+; this driver additionally requires Error+
$logger->add(
    new CloudWatchDriver($client, '/app/alerts', 'web', $formatter),
    matcher: new IsLevelOrHigher(LogLevel::Error),
    group: 'prod',
);
```

### Runtime group management

Groups can be added and removed at runtime. All drivers registered to a removed group stop receiving log items immediately.

```php
// On entering a request context
$logger->addGroup('request', matcher: new HasContext('request_id'));
$logger->add(new CloudWatchDriver($client, '/app/requests', 'web', $formatter), group: 'request');

// On exit
$logger->removeGroup('request');
```

Constraints:
- Group IDs must be unique — `addGroup()` throws if the ID already exists.
- `removeGroup('default')` throws — the default group cannot be removed.
- `add()` throws if the specified group does not exist.

---

## Processors

Processors transform log items before they are dispatched to drivers. Register them with `addProcessor()` — they run in registration order after the channel is stamped.

### TagProcessor

Stamps a fixed set of key-value pairs on every log item:

```php
use Ordinary\Log\Processor\TagProcessor;

$logger->addProcessor(new TagProcessor([
    'env'     => 'production',
    'release' => 'v2.3.1',
    'service' => 'payment-api',
]));
```

### UidProcessor

Generates a unique identifier at construction time and adds it to every log item. Create a new instance per request to correlate all log entries for a single operation:

```php
use Ordinary\Log\Processor\UidProcessor;

$logger->addProcessor(new UidProcessor());                           // 'uid' — 7 hex chars
$logger->addProcessor(new UidProcessor('request_id', length: 16));  // custom key + length

// Bring your own generator — must return a non-empty string
$logger->addProcessor(new UidProcessor(generator: fn () => Uuid::v4()->toString()));
```

Throws `\UnexpectedValueException` at construction if the generator returns an empty string.

### MemoryUsageProcessor

Adds the current memory usage (in bytes) to every log item:

```php
use Ordinary\Log\Processor\MemoryUsageProcessor;

$logger->addProcessor(new MemoryUsageProcessor());                   // adds 'memory.usage'
$logger->addProcessor(new MemoryUsageProcessor(includePeak: true));  // also 'memory.peak_usage'
$logger->addProcessor(new MemoryUsageProcessor(realUsage: true));    // system-allocated bytes
```

### WebProcessor

Adds HTTP request details to every log item. Supply a PSR-7 `ServerRequestInterface`
or a raw server params array — nothing is read from globals:

```php
use Ordinary\Log\Processor\WebProcessor;

// PSR-7 request: native methods used first, server params as fallback
$logger->addProcessor(new WebProcessor($psrRequest));
// Adds: request.url, request.ip, request.method, request.server, request.referrer, request.user_agent

// Raw server params array (useful in tests or CLI scripts)
$logger->addProcessor(new WebProcessor(['REQUEST_URI' => '/test', 'REMOTE_ADDR' => '127.0.0.1']));

// Include additional server param keys
$logger->addProcessor(new WebProcessor($psrRequest, extraFields: ['HTTP_X_REQUEST_ID']));
// Also adds: request.http_x_request_id
```

When a PSR-7 request is provided, URL and method come from `getUri()` / `getMethod()`,
headers from `getHeaderLine()`, and IP / extra fields from `getServerParams()`. Passing
`null` (the default) adds no context.

### IntrospectionProcessor

Adds the file, line, class, and function of the actual log call site by inspecting the call stack:

```php
use Ordinary\Log\Processor\IntrospectionProcessor;

$logger->addProcessor(new IntrospectionProcessor());
// Adds: log.file, log.line, log.class, log.function
```

If your application wraps ordinary/log in its own logger class, exclude that namespace from the walk:

```php
$logger->addProcessor(new IntrospectionProcessor(skipNamespaces: ['App\\Logging\\']));
```

### CallableProcessor

Wraps a closure as a processor for one-off transformations:

```php
use Ordinary\Log\CallableProcessor;

$logger->addProcessor(new CallableProcessor(
    fn(ImmutableLogEntryInterface $item) => $item->withContext(['request_id' => $requestId]),
));
```

### Creating a custom processor

Implement `LogProcessorInterface` with a single `process()` method:

```php
use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogProcessorInterface;

final class TenantProcessor implements LogProcessorInterface
{
    public function __construct(private readonly string $tenantId) {}

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        if ($logItem instanceof ImmutableLogEntryInterface) {
            return $logItem->withContext(['tenant_id' => $this->tenantId]);
        }

        return new LogEntry(
            $logItem->level,
            $logItem->message,
            $logItem->dateTime,
            \array_merge($logItem->context, ['tenant_id' => $this->tenantId]),
        );
    }
}
```

---

## Creating a Custom Logger

Implement `LoggerInterface` and use `LoggerTrait`. The trait provides the eight named methods; you only need to implement `log()`.

```php
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LoggerInterface;
use Ordinary\Log\LoggerTrait;

final class TenantLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly string $tenantId,
        private readonly Logger $logger,
    ) {}

    public function log(LogEntryInterface $logItem): void
    {
        $this->logger->log(
            $logItem->withContext(['tenant_id' => $this->tenantId]),
        );
    }
}
```

---

## Controlling Timestamps

All log items created via the named helper methods (`info()`, `error()`, etc.) are timestamped using `Logger`'s `$clock` parameter. The default is `UtcClock`. Inject a `Psr\Clock\ClockInterface` to control timestamps in tests or to use a different time source:

```php
use Ordinary\Log\Logger;
use Ordinary\Log\UtcClock;

// Production — default UTC clock
$logger = new Logger();

// Tests — inject a MutableClock to control time
$clock = new MutableClock(new DateTimeImmutable('2024-01-01T00:00:00Z'));
$logger = new Logger(clock: $clock);
```

The same pattern applies to `DeduplicatingDriver` — inject a clock to drive the deduplication window in tests without relying on real wall-clock time.

---

## Creating a Custom Driver

Implement `LogDriverInterface` with a single `handleLog()` method. Do not add matcher or dispatcher logic — `Logger` handles that.

```php
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;

final class SlackDriver implements LogDriverInterface
{
    public function __construct(private readonly string $webhookUrl) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        // post formatted message to Slack webhook...
    }
}
```

### Flushing buffered state

Call `Logger::flush()` to drain any buffered items held by drivers that implement `FlushableInterface`. Every registered driver that implements this interface is flushed, in group order, regardless of whether an earlier one throws. The first exception, if any, is re-thrown after all drivers have been attempted.

```php
// Typically called once, at request or job end
$logger->flush();
```

### Bufferable drivers

Implement `FlushableInterface` alongside `LogDriverInterface` to participate in flush propagation. Wrapping drivers **must** cascade to the inner driver:

```php
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;

final class MyBufferingDriver implements LogDriverInterface, FlushableInterface
{
    private array $buffer = [];

    public function __construct(private readonly LogDriverInterface $inner) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        $this->buffer[] = $logItem;
    }

    public function flush(): void
    {
        foreach ($this->buffer as $item) {
            $this->inner->handleLog($item);
        }
        $this->buffer = [];

        // Cascade to inner if it is also flushable
        if ($this->inner instanceof FlushableInterface) {
            $this->inner->flush();
        }
    }
}
```

### Bulk-write drivers

If your backend supports writing multiple items in a single call, implement `LogBatchDriverInterface`. `BufferingDriver` detects this interface during flush and calls `handleLogBatch()` with the entire buffer at once.

```php
use Ordinary\Log\LogBatch;
use Ordinary\Log\LogBatchDriverInterface;
use Ordinary\Log\LogEntryInterface;

final class ElasticsearchDriver implements LogBatchDriverInterface
{
    public function handleLog(LogEntryInterface $logItem): void
    {
        $this->client->index(['body' => $this->format($logItem)]);
    }

    public function handleLogBatch(LogBatch $batch): void
    {
        $body = [];
        foreach ($batch->items as $item) {
            $body[] = ['index' => ['_index' => 'logs']];
            $body[] = $this->format($item);
        }
        $this->client->bulk(['body' => $body]);
    }
}
```

### Synchronous drivers

If your driver must always execute immediately — bypassing the async dispatcher — implement `SynchronousDriverInterface` instead:

```php
use Ordinary\Log\SynchronousDriverInterface;

final class ImmediateStreamDriver implements SynchronousDriverInterface
{
    public function handleLog(LogEntryInterface $logItem): void
    {
        // always runs synchronously even when a dispatcher is set
    }
}
```

---

## Matchers

Matchers implement `LogMatcherInterface` and filter which log items a driver or group processes.

### Using matchers

```php
use Ordinary\Log\Matcher\IsAll;
use Ordinary\Log\Matcher\IsAny;
use Ordinary\Log\Matcher\IsLevel;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\Matcher\IsLevelOrLower;
use Ordinary\Log\Matcher\IsNot;

// Severity thresholds
new IsLevelOrHigher(LogLevel::Error)   // Error, Critical, Alert, Emergency
new IsLevelOrLower(LogLevel::Notice)   // Debug, Info, Notice

// Exact level matching (variadic)
new IsLevel(LogLevel::Warning, LogLevel::Error)

// Composition
new IsNot(new IsLevel(LogLevel::Debug))
new IsAll([new IsLevelOrHigher(LogLevel::Warning), new IsNot(new IsLevel(LogLevel::Warning))])
new IsAny([new IsLevel(LogLevel::Debug), new IsLevelOrHigher(LogLevel::Error)])
```

Matchers compose without limit. Reuse the same instance across groups and drivers:

```php
$prodMatcher = new IsLevelOrHigher(LogLevel::Warning);

$logger->addGroup('prod', matcher: $prodMatcher);
$logger->add($cloudWatchDriver, group: 'prod');
$logger->add($fileDriver, group: 'prod');

// Dev extends prod — receives everything prod does, plus debug
$logger->addGroup('dev', matcher: new IsAny([$prodMatcher, new IsLevel(LogLevel::Debug)]));
$logger->add($localStreamDriver, group: 'dev');
```

### Creating a matcher

Implement `LogMatcherInterface` with a single `matches()` method:

```php
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogMatcherInterface;

final class HasContext implements LogMatcherInterface
{
    public function __construct(private readonly string $key) {}

    public function matches(LogEntryInterface $logItem): bool
    {
        return array_key_exists($this->key, $logItem->context);
    }
}
```

---

## Error Fallbacks

When a driver throws, the exception is wrapped in a `LogFailureException` that carries the original exception, the log item, and the failing driver. `Logger` passes it to the configured failure handler and then continues to the next driver.

By default, `Logger` uses `ErrorLogFailureHandler`, which writes the failure to PHP's error log via `error_log()`. Override it in the constructor:

```php
use Ordinary\Log\FailureHandler\NoOpFailureHandler;
use Ordinary\Log\FailureHandler\SyslogFailureHandler;
use Ordinary\Log\FailureHandler\StderrFailureHandler;
use Ordinary\Log\FailureHandler\ErrorLogFailureHandler;

// Default — writes to PHP error log
$logger = new Logger(onFailure: new ErrorLogFailureHandler());

// Write to syslog
$logger = new Logger(onFailure: new SyslogFailureHandler());

// Write to STDERR
$logger = new Logger(onFailure: new StderrFailureHandler());

// Silently discard (explicit no-op)
$logger = new Logger(onFailure: new NoOpFailureHandler());
```

### LogFailureException

```php
$e->getMessage();        // "Log dispatch failed for [error] 'msg': <original message>"
$e->getPrevious();       // the original \Throwable
$e->getLogItem();        // the LogEntryInterface being dispatched
$e->getFailingDriver();  // the driver that threw
```

### Custom failure handler

Implement `LogFailureHandlerInterface`:

```php
use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;

final class SentryFailureHandler implements LogFailureHandlerInterface
{
    public function handleLogFailure(LogFailureExceptionInterface $e): void
    {
        \Sentry\captureException($e->getPrevious() ?? $e);
    }
}
```

---

## Async Dispatching

Pass a `$dispatcher` closure to `Logger` to defer driver calls. The closure receives each driver invocation as a zero-argument closure.

The `shouldLog()` / matcher checks always run **synchronously** before anything is queued, so filtered items are never dispatched.

### With revolt/event-loop

[revolt/event-loop](https://github.com/revoltphp/event-loop) is the shared backend for Amp 3.x and ReactPHP 3.x.

```bash
composer require revolt/event-loop
```

```php
use Revolt\EventLoop;
use Ordinary\Log\Logger;

$logger = new Logger(
    dispatcher: fn(\Closure $fn) => EventLoop::queue($fn),
);

$logger->add(new StreamDriver(fopen('/var/log/app.log', 'a'), $formatter));

$logger->info('Queued — not written yet');

EventLoop::run(); // flushes all queued writes
```

Each driver call is dispatched as its own unit. Drivers implementing `SynchronousDriverInterface` are always invoked immediately, even when a dispatcher is set.

---

## Migrating from Monolog

This section maps common Monolog patterns to their ordinary/log equivalents.

### Logger setup

```php
// Monolog
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('app');
$log->pushHandler(new StreamHandler('/var/log/app.log', Logger::WARNING));

// ordinary/log
use Ordinary\Log\Logger;
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\JsonFormatter;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\LogLevel;

$logger = new Logger(channel: 'app');
$logger->add(
    new StreamDriver(fopen('/var/log/app.log', 'a'), new JsonFormatter()),
    matcher: new IsLevelOrHigher(LogLevel::Warning),
);
```

### PSR-3 / framework integration

```php
// Monolog — already implements Psr\Log\LoggerInterface natively
$container->bind(Psr\Log\LoggerInterface::class, fn() => $log);

// ordinary/log — one extra call
$container->bind(Psr\Log\LoggerInterface::class, fn() => $logger->toPsr());
```

### Handlers → Drivers

| Monolog handler | ordinary/log driver |
|---|---|
| `StreamHandler` | `StreamDriver` |
| `RotatingFileHandler` | `RotatingStreamDriver` |
| `SyslogHandler` | `SyslogDriver` |
| `CloudWatchLogsHandler` | `CloudWatchDriver` |
| `BufferHandler` | `BufferingDriver` |
| `FingersCrossedHandler` | `FingersCrossedDriver` |
| `NullHandler` | `NullDriver` |
| `TestHandler` | `TestDriver` |

### Processors

```php
// Monolog
$log->pushProcessor(function (array $record): array {
    $record['extra']['request_id'] = $requestId;
    return $record;
});

// ordinary/log
use Ordinary\Log\CallableProcessor;

$logger->addProcessor(new CallableProcessor(
    fn($item) => $item->withContext(['request_id' => $requestId]),
));
```

### Filtering by level

```php
// Monolog — minimum level on each handler
$log->pushHandler(new StreamHandler(STDERR, Logger::ERROR));

// ordinary/log — matcher on each driver (or on the group)
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\LogLevel;

$logger->add(new StreamDriver(STDERR), matcher: new IsLevelOrHigher(LogLevel::Error));
```

### Channels

```php
// Monolog — separate Logger instance per channel
$paymentLog = new Logger('payment');

// ordinary/log — set on the Logger constructor; one logger, one channel
$paymentLogger = new Logger(channel: 'payment');

// Appears as a top-level "channel" field in JsonFormatter output
```

### Exception logging

```php
// Monolog
$log->error('Charge failed', ['exception' => $e]);

// ordinary/log — identical; RESERVED_EXCEPTION is also "exception"
$logger->error('Charge failed', ['exception' => $e]);
```
