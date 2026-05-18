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
use Ordinary\Log\JsonLogFormatter;

$logger = new Logger();
$logger->add(new StreamDriver(STDOUT, new JsonLogFormatter()));

$logger->info('User signed in', ['user_id' => 42]);
$logger->warning('Cache miss on key {key}', ['key' => 'user:42:prefs']);
$logger->error('Payment failed', ['order_id' => 'ORD-999', 'exception' => $e]);
```

Message strings support `{key}` placeholder interpolation using context values.

### Log levels (lowest → highest severity)

`Debug` → `Info` → `Notice` → `Warning` → `Error` → `Critical` → `Alert` → `Emergency`

---

## Log Drivers

Drivers implement `LogDriverInterface` with a single `handleLog()` method. They are pure I/O — matching, dispatching, and failure handling are all managed by `Logger`.

### StreamDriver

Writes a formatted line to any writable stream resource.

```php
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\JsonLogFormatter;

// Write JSON lines to a file
$logger->add(new StreamDriver(
    stream: fopen('/var/log/app.log', 'a'),
    formatter: new JsonLogFormatter(),
));

// Write errors and above to STDERR
$logger->add(
    new StreamDriver(STDERR, new JsonLogFormatter()),
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
    formatter: new JsonLogFormatter(),
));
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

## Creating a Custom Logger

Implement `LoggerInterface` and use `LoggerTrait`. The trait provides the eight named methods; you only need to implement `log()`.

```php
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LoggerInterface;
use Ordinary\Log\LoggerTrait;

final class TenantLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly string $tenantId,
        private readonly Logger $logger,
    ) {}

    public function log(LogItemInterface $logItem): void
    {
        $this->logger->log(
            $logItem->withContext(['tenant_id' => $this->tenantId]),
        );
    }
}
```

---

## Creating a Custom Driver

Implement `LogDriverInterface` with a single `handleLog()` method. Do not add matcher or dispatcher logic — `Logger` handles that.

```php
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogItemInterface;

final class SlackDriver implements LogDriverInterface
{
    public function __construct(private readonly string $webhookUrl) {}

    public function handleLog(LogItemInterface $logItem): void
    {
        // post formatted message to Slack webhook...
    }
}
```

### Synchronous drivers

If your driver must always execute immediately — bypassing the async dispatcher — implement `SynchronousDriverInterface` instead:

```php
use Ordinary\Log\SynchronousDriverInterface;

final class ImmediateStreamDriver implements SynchronousDriverInterface
{
    public function handleLog(LogItemInterface $logItem): void
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
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

final class HasContext implements LogMatcherInterface
{
    public function __construct(private readonly string $key) {}

    public function matches(LogItemInterface $logItem): bool
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
$e->getLogItem();        // the LogItemInterface being dispatched
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

## PSR-3 Compatibility

`PsrLoggerAdapter` wraps any `LoggerInterface` to satisfy PSR-3's `\Psr\Log\LoggerInterface`:

```php
use Ordinary\Log\Psr\PsrLoggerAdapter;

$psrLogger = new PsrLoggerAdapter($logger);

// Compatible with any library that typehints Psr\Log\LoggerInterface
$psrLogger->error('Something failed', ['context' => 'value']);
```
