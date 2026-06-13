# ordinary/cli

A PHP 8.5 CLI argument parser and subcommand router. Declare options and commands with typed builders or PHP attributes, receive a typed `ParsedInput` in every handler, and get auto-generated help text — with zero framework dependencies.

## Key design principles

- **Declare once, validate always** — options and positional arguments are declared upfront; the parser errors on anything undeclared.
- **Positional option scoping** — options before a subcommand token belong to the parent; options after belong to the subcommand. Global options propagate down automatically.
- **Space-separated values only** — `--config value` and `-c value` are the only supported syntaxes. `--config=value` is intentionally not supported; this keeps the grammar unambiguous and the parser simple.
- **Flag counts** — flags record how many times they appear: `-vvv` yields `flag('verbose') === 3`.
- **Typed parsed results** — handlers receive `ParsedInputInterface` with `flag()`, `value()`, `values()`, `argument()`, and `arguments()`; no `mixed` returns, no casting.
- **Compile once, run once** — `compile()` validates the command tree at build time and returns an immutable dispatcher.
- **No magic** — zero autowiring, no convention scanning, no hidden behavior.

## Installation

```bash
composer require ordinary/cli
```

Requires PHP ≥ 8.5. Zero runtime dependencies.

## Quick start

```php
<?php
declare(strict_types=1);

use Ordinary\Cli\CommandRouter;
use Ordinary\Cli\Console;
use Ordinary\Cli\ExitCode;
use Ordinary\Cli\Input\ParsedInputInterface;

$router = new CommandRouter(name: 'myapp', version: '1.0.0');

// Global option — available to every command at any depth
$router->option('verbose', 'v')
    ->flag()
    ->global()
    ->description('Enable verbose output');

// Root-level option — only valid before any subcommand
$router->option('config', 'c')
    ->value()
    ->description('Path to config file')
    ->default('/etc/myapp.conf');

// Simple command
$router->command('version')
    ->description('Print version and exit')
    ->handler(fn(ParsedInputInterface $i, Console $io): int => (
        $io->output->writeln('myapp 1.0.0') ?? ExitCode::Success->value
    ));

// Nested commands
$db = $router->command('db')->description('Database utilities');

$db->command('migrate')
   ->description('Apply pending migrations')
   ->option('dry-run', 'd')->flag()->description('Preview without writing')
   ->option('batch', 'b')->value()->description('Batch size')->default('all')
   ->handler(function (ParsedInputInterface $input, Console $io): int {
       if ($input->flag('verbose')) { // global — available everywhere
           $io->output->writeln('[verbose] starting migrate');
       }
       $io->output->writeln('dry-run: ' . ($input->flag('dry-run') ? 'yes' : 'no'));
       return ExitCode::Success->value;
   });

$db->command('seed')
   ->description('Seed the database')
   ->argument('table')->optional()->description('Limit to one table')
   ->handler(fn(ParsedInputInterface $i, Console $io): int => ExitCode::Success->value);

$dispatcher = $router->compile();
exit($dispatcher->run($_SERVER['argv'], Console::fromStreams()));
```

## Option types

### Flags

Flags record the number of occurrences. They never consume a value token.

```php
$router->option('verbose', 'v')->flag()->global();

// CLI: myapp -vvv migrate
// $input->flag('verbose') === 3
// if ($input->flag('verbose')) — truthy check
```

### Value options

Value options consume the next token as their string value.

```php
$cmd->option('config', 'c')->value()->description('Config file');

// CLI: myapp --config /etc/app.conf
// CLI: myapp -c /etc/app.conf
// $input->value('config') === '/etc/app.conf'
```

> **Note:** The `--option=value` syntax is not supported. Only space-separated values
> are accepted (`--option value`). This is a deliberate design decision for parser
> simplicity. Document this constraint in your application's help text or man page
> if your users are accustomed to the `=` form.

### Repeated value options

```php
// Store last (default): subsequent occurrences overwrite earlier ones
$cmd->option('env', 'e')->value()->description('Environment');

// Store first: first occurrence wins, rest are consumed and discarded
$cmd->option('env', 'e')->value()->repeat(OptionRepeat::StoreFirst);

// Store list: collect all occurrences
$cmd->option('file', 'f')->value()->repeat(OptionRepeat::StoreList)->description('Files');

// CLI: myapp process --file a.txt --file b.txt --file c.txt
// $input->values('file') === ['a.txt', 'b.txt', 'c.txt']
```

## Option scoping

Options declared with `->global()` are available to all descendant commands without redeclaration. The handler receives them identically to locally-declared options — no separate accessor needed.

```php
$router->option('verbose', 'v')->flag()->global();

// CLI: myapp --verbose db migrate
// Inside the migrate handler: $input->flag('verbose') === 1
```

Options without `->global()` are local — they are only valid before the command that declares them.

## Subcommands

Commands can be nested to any depth. Options before a subcommand token are parsed by the parent; options after belong to the subcommand.

```php
$db = $router->command('db');
$migrate = $db->command('migrate');
$seed = $db->command('seed');

// CLI: myapp db migrate --dry-run
// CLI: myapp --verbose db migrate   (--verbose is global, before db is fine)
// CLI: myapp db --unknown-flag      Error: unknown option
```

A command may have **either** subcommands **or** positional arguments — not both. This is enforced at `compile()` time.

## Positional arguments

```php
$cmd->argument('environment')
    ->required()
    ->enum(Environment::class)           // validated against all case values
    ->description('Target environment');

$cmd->argument('target')
    ->optional()
    ->description('Migration target version');

$cmd->argument('files')
    ->variadic()                         // consumes all remaining tokens; must be last
    ->description('Files to process');
```

`ParsedInputInterface::argument('name')` returns `?string`. For enum-constrained arguments, `BackedEnum::from($input->argument('env'))` is guaranteed safe after parsing.

## `--` terminator

Everything after `--` is treated as a positional argument, regardless of leading dashes:

```bash
myapp process -- --not-an-option positional-arg
```

## Built-in flags

`--help` / `-h` and `--version` / `-V` are auto-wired:

- `--help` anywhere in the argument vector prints help for the matched command level and exits 0.
- `--version` prints `<appname> <version>` (when a version was provided) and exits 0.

## Testing

Handlers are closures that receive `ParsedInputInterface` and `Console`. Use `BufferedOutput` and `BufferedInput` in tests — no mocking required:

```php
use Ordinary\Cli\Console;
use Ordinary\Cli\Io\BufferedInput;
use Ordinary\Cli\Io\BufferedOutput;

$out = new BufferedOutput();
$err = new BufferedOutput();
$console = new Console(new BufferedInput(), $out, $err);

$exitCode = $dispatcher->run(['myapp', 'migrate', '--dry-run'], $console);

assertSame(0, $exitCode);
assertStringContainsString('dry-run: yes', $out->content());
```

## Attribute-based registration

```php
use Ordinary\Cli\Attribute\Command;
use Ordinary\Cli\Attribute\CommandArgument;
use Ordinary\Cli\Attribute\CommandOption;
use Ordinary\Cli\Argument\ArgumentMode;
use Ordinary\Cli\Option\OptionType;

// Class-level: represents the 'db' command; methods become subcommands
#[Command(name: 'db', description: 'Database utilities')]
#[CommandOption(long: 'connection', short: 'C', type: OptionType::Value, description: 'DB connection')]
final class DatabaseCommands
{
    #[Command(name: 'migrate', description: 'Apply pending migrations')]
    #[CommandOption(long: 'dry-run', short: 'd', type: OptionType::Flag)]
    public function migrate(ParsedInputInterface $input, Console $console): int
    {
        return 0;
    }

    #[Command(name: 'seed', description: 'Seed the database')]
    #[CommandArgument(name: 'table', mode: ArgumentMode::Optional)]
    public function seed(ParsedInputInterface $input, Console $console): int
    {
        return 0;
    }
}

// Standalone function
#[Command(name: 'ping', description: 'Check connectivity')]
function pingCommand(ParsedInputInterface $input, Console $console): int { return 0; }
```

```php
use Ordinary\Cli\Attribute\AttributeCommandLoader;

$loader = new AttributeCommandLoader($router);
$loader->loadClass(DatabaseCommands::class);
$loader->loadFunction('pingCommand');
$loader->loadDirectory(__DIR__ . '/Commands');  // token-parses, no execution

$dispatcher = $router->compile();
```

## Exception handling

Every exception thrown by this package implements `Ordinary\Cli\Exception\ExceptionInterface`:

```php
use Ordinary\Cli\Exception\ExceptionInterface;
use Ordinary\Cli\Exception\LogicException;   // compile-time wiring errors
use Ordinary\Cli\Exception\ParseException;   // never surfaces from run()

try {
    $dispatcher = $router->compile();          // LogicException on bad wiring
} catch (LogicException $e) {
    // missing handler, subcommands+args on same command, duplicate short names
}
```

Parse errors (unknown option, missing required, unknown command) are handled internally by `CommandDispatcher::run()`: they write to `$console->error`, print help text, and return exit code 2. They never surface to the caller.

## Exit codes

```php
use Ordinary\Cli\ExitCode;

ExitCode::Success->value  // 0
ExitCode::Error->value    // 1
ExitCode::Usage->value    // 2  (parse errors, returned automatically)
```
