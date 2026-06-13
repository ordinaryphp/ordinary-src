<?php

declare(strict_types=1);

namespace Ordinary\Cli\Tests\Parser;

use Ordinary\Cli\CommandRouter;
use Ordinary\Cli\Console;
use Ordinary\Cli\ExitCode;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Io\BufferedInput;
use Ordinary\Cli\Io\BufferedOutput;
use PHPUnit\Framework\TestCase;

final class SubcommandDispatchTest extends TestCase
{
    private function console(BufferedOutput $out = new BufferedOutput(), BufferedOutput $err = new BufferedOutput()): Console
    {
        return new Console(new BufferedInput(), $out, $err);
    }

    public function testSubcommandDispatchesCorrectHandlerAndNotParent(): void
    {
        $rootCalled   = false;
        $migrateCalled = false;

        $router = new CommandRouter(name: 'app');
        $router->handler(function (ParsedInputInterface $i, Console $c) use (&$rootCalled): int {
            $rootCalled = true;
            return ExitCode::Success->value;
        });

        $router->command('db')
            ->command('migrate')
            ->handler(function (ParsedInputInterface $i, Console $c) use (&$migrateCalled): int {
                $migrateCalled = true;
                return ExitCode::Success->value;
            });

        $router->compile()->run(['app', 'db', 'migrate'], $this->console());

        $this->assertTrue($migrateCalled);
        $this->assertFalse($rootCalled);
    }

    public function testHandlerExitCodeIsPropagatedUnchangedByRun(): void
    {
        $router = new CommandRouter(name: 'app');
        $router->command('fail')
            ->handler(fn(ParsedInputInterface $i, Console $c): int => 42);

        $exitCode = $router->compile()->run(['app', 'fail'], $this->console());
        $this->assertSame(42, $exitCode);
    }

    public function testGlobalOptionFromRootIsAccessibleInNestedSubcommandHandler(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $router->option('verbose', 'v')->flag()->global();

        $router->command('db')
            ->command('migrate')
            ->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
                $captured = $i->flag('verbose');
                return ExitCode::Success->value;
            });

        $router->compile()->run(['app', '--verbose', 'db', 'migrate'], $this->console());
        $this->assertSame(1, $captured);
    }

    public function testGlobalFlagCountPropagatesAcrossMultipleLevels(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $router->option('verbose', 'v')->flag()->global();

        $router->command('db')
            ->command('migrate')
            ->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
                $captured = $i->flag('verbose');
                return ExitCode::Success->value;
            });

        $router->compile()->run(['app', '-vvv', 'db', 'migrate'], $this->console());
        $this->assertSame(3, $captured);
    }

    public function testLocalOptionOnParentIsNotAvailableInChildHandler(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $router->option('config', 'c')->value();  // local, not global

        $router->command('db')
            ->command('migrate')
            ->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
                $captured = $i->value('config');
                return ExitCode::Success->value;
            });

        $router->compile()->run(['app', '--config', '/etc/app.conf', 'db', 'migrate'], $this->console());
        $this->assertNull($captured);
    }

    public function testUnrecognisedSubcommandWritesToErrorAndReturnsExitCodeTwo(): void
    {
        $router = new CommandRouter(name: 'app');
        $router->command('db')
            ->command('migrate')
            ->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'db', 'nonexistent'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('Unknown command', $err->content());
    }

    public function testOptionAfterSubcommandBelongsToSubcommand(): void
    {
        $rootConfig   = null;
        $migrateFlag  = null;

        $router = new CommandRouter(name: 'app');
        $router->option('config', 'c')->value();
        $router->handler(function (ParsedInputInterface $i, Console $c) use (&$rootConfig): int {
            $rootConfig = $i->value('config');
            return ExitCode::Success->value;
        });

        $cmd = $router->command('migrate');
        $cmd->option('dry-run', 'd')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$rootConfig, &$migrateFlag): int {
            $rootConfig  = $i->value('config');   // should be null (local to root)
            $migrateFlag = $i->flag('dry-run');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'migrate', '--dry-run'], $this->console());

        $this->assertNull($rootConfig);
        $this->assertSame(1, $migrateFlag);
    }

    public function testRootHandlerRunsWhenNoSubcommandProvided(): void
    {
        $called = false;
        $router = new CommandRouter(name: 'app');
        $router->handler(function (ParsedInputInterface $i, Console $c) use (&$called): int {
            $called = true;
            return ExitCode::Success->value;
        });
        $router->command('sub')
            ->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Error->value);

        $router->compile()->run(['app'], $this->console());
        $this->assertTrue($called);
    }

    public function testHelpFlagWritesFormattedDescriptionToOutputAndReturnsZero(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->description('Run the thing');
        $cmd->option('verbose', 'v')->flag()->description('Enable verbose output');
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $out = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'run', '--help'], $this->console($out));

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('Run the thing', $out->content());
        $this->assertStringContainsString('--verbose', $out->content());
        $this->assertStringContainsString('--help', $out->content());
    }

    public function testVersionFlagWritesVersionStringAndReturnsZero(): void
    {
        $router = new CommandRouter(name: 'myapp', version: '2.1.0');
        $router->command('run')
            ->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $out = new BufferedOutput();
        $exitCode = $router->compile()->run(['myapp', '--version'], $this->console($out));

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('myapp 2.1.0', $out->content());
    }
}
