# ordinary/error

PHP error and exception handler management.

## Overview

This package provides two invokable handler classes — `ErrorHandler` and `ExceptionHandler` — that can be registered with PHP's `set_error_handler()` and `set_exception_handler()`. Each accepts multiple handlers and optional matchers to route only matching throwables to each handler.

PHP errors are always converted to `ErrorException` before dispatch, so a single `ThrowableHandlerInterface` implementation handles both errors and exceptions without duplicated logic.

## Installation

```bash
composer require ordinary/error
```

## Quick start

```php
use Ordinary\Error\ErrorHandler;
use Ordinary\Error\ExceptionHandler;
use Ordinary\Error\Handler\LogHandler;
use Ordinary\Error\Matcher\IsErrorSeverity;
use Ordinary\Error\Matcher\IsInstanceOf;

// Register an error handler that logs warnings and notices
$errorHandler = new ErrorHandler();
$errorHandler->add(
    new LogHandler($logger),
    new IsErrorSeverity(E_WARNING, E_NOTICE, E_DEPRECATED),
);
ErrorHandler::register($errorHandler);

// Register an exception handler for uncaught throwables
$exceptionHandler = new ExceptionHandler();
$exceptionHandler->add(new LogHandler($logger));
ExceptionHandler::register($exceptionHandler);
```

## Smart registration

Calling `register()` when a handler of the same type is already registered appends the new handlers to the existing instance rather than replacing it. This lets a framework and plugins each contribute handlers independently:

```php
// Framework registers first
$framework = new ErrorHandler();
$framework->add(new LogHandler($frameworkLogger));
ErrorHandler::register($framework);

// Plugin registers later — handlers are merged into $framework
$plugin = new ErrorHandler();
$plugin->add(new LogHandler($pluginLogger));
$effective = ErrorHandler::register($plugin); // returns $framework
```

Pass `chainCurrent: true` to wrap any previously registered PHP handler and keep it in the chain:

```php
ErrorHandler::register($handler, chainCurrent: true);
```

## Matchers

Matchers filter which throwables reach a handler.

| Matcher | Description |
|---------|-------------|
| `IsErrorSeverity(int ...$severities)` | Matches `\ErrorException` whose severity overlaps the given flags |
| `IsInstanceOf(string $class)` | Matches throwables that are instances of a class or interface |
| `IsAll(matcher, ...)` | All matchers must pass (AND) |
| `IsAny(matcher, ...)` | Any matcher must pass (OR) |
| `IsNot(matcher)` | Inverts a matcher |
| `CallableMatcher(callable)` | Wraps a `callable(\Throwable): bool` |

```php
use Ordinary\Error\Matcher\IsAll;
use Ordinary\Error\Matcher\IsErrorSeverity;
use Ordinary\Error\Matcher\IsNot;

// Only errors that are NOT deprecated notices
$handler->add(
    new LogHandler($logger),
    new IsNot(new IsErrorSeverity(E_DEPRECATED, E_USER_DEPRECATED)),
);
```

## Built-in handlers

### `LogHandler`

Logs each throwable through a PSR-3 logger. PHP error severity levels are mapped to PSR-3 levels automatically:

| PHP severity | PSR-3 level |
|-------------|-------------|
| `E_ERROR`, `E_USER_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR` | `critical` |
| `E_RECOVERABLE_ERROR` | `error` |
| `E_WARNING`, `E_USER_WARNING`, `E_CORE_WARNING`, `E_COMPILE_WARNING` | `warning` |
| `E_NOTICE`, `E_USER_NOTICE` | `notice` |
| `E_DEPRECATED`, `E_USER_DEPRECATED`, `E_STRICT` | `info` |
| Other throwables or unmapped severities | `$defaultLevel` (default: `error`) |

```php
use Ordinary\Error\Handler\LogHandler;
use Psr\Log\LogLevel;

$handler->add(new LogHandler($logger, defaultLevel: LogLevel::CRITICAL));
```

### `CallableHandler`

Wraps any `callable(\Throwable): void` as a handler:

```php
use Ordinary\Error\CallableHandler;

$handler->add(new CallableHandler(function (\Throwable $t): void {
    // custom logic
}));
```

### `NativeErrorHandlerAdapter`

Adapts a native `set_error_handler` callback (signature `(int, string, string, int): bool`) for use inside an `ErrorHandler`:

```php
use Ordinary\Error\NativeErrorHandlerAdapter;

$handler->add(new NativeErrorHandlerAdapter($existingCallback));
```

## Async dispatch

Both `ErrorHandler` and `ExceptionHandler` accept an optional `$dispatcher` closure. Non-synchronous handlers are passed to the dispatcher as zero-argument operations:

```php
use Ordinary\Error\ErrorHandler;

$handler = new ErrorHandler(
    dispatcher: fn(\Closure $op) => $eventLoop->queue($op),
);
```

Handlers implementing `SynchronousHandlerInterface` bypass the dispatcher and always run immediately.

## Handler failure handling

When an asynchronously dispatched handler throws, the exception is forwarded to a `HandlerFailureHandlerInterface`:

| Implementation | Behaviour |
|---------------|-----------|
| `ErrorLogFailureHandler` | Writes to PHP's error log (default) |
| `NoOpFailureHandler` | Silently discards failures |
| `StderrFailureHandler` | Writes to STDERR |

```php
use Ordinary\Error\ExceptionHandler;
use Ordinary\Error\FailureHandler\StderrFailureHandler;

$handler = new ExceptionHandler(onFailure: new StderrFailureHandler());
```

## Error suppression

`ErrorHandler` respects the `@` error-suppression operator: errors that fall outside the current `error_reporting()` mask return `false`, allowing PHP's built-in handler to process them normally.
