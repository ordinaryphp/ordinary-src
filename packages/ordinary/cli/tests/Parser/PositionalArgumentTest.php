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

final class PositionalArgumentTest extends TestCase
{
    private function console(BufferedOutput $out = new BufferedOutput(), BufferedOutput $err = new BufferedOutput()): Console
    {
        return new Console(new BufferedInput(), $out, $err);
    }

    public function testDoubleDashStopsOptionParsingForSubsequentTokens(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = [$i->flag('verbose'), $i->arguments()];
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '-v', '--', '--not-a-flag', 'positional'], $this->console());

        $this->assertSame([1, ['--not-a-flag', 'positional']], $captured);
    }

    public function testDoubleDashAloneCollectsNoPositionals(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $router->command('run')
            ->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
                $captured = $i->arguments();
                return ExitCode::Success->value;
            });

        $router->compile()->run(['app', 'run', '--'], $this->console());
        $this->assertSame([], $captured);
    }

    public function testRequiredArgumentCapturedFromPositionalToken(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('deploy');
        $cmd->argument('target')->required()->description('Deploy target');
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->argument('target');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'deploy', 'prod'], $this->console());
        $this->assertSame('prod', $captured);
    }

    public function testOptionalArgumentReturnsNullWhenAbsent(): void
    {
        $captured = 'sentinel';
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->argument('target')->optional();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->argument('target');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run'], $this->console());
        $this->assertNull($captured);
    }

    public function testVariadicArgumentCollectsAllRemainingTokens(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('process');
        $cmd->argument('files')->variadic();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->arguments();
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'process', 'a.txt', 'b.txt', 'c.txt'], $this->console());
        $this->assertSame(['a.txt', 'b.txt', 'c.txt'], $captured);
    }

    public function testMissingRequiredArgumentWritesToErrorAndReturnsExitCodeTwo(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('deploy');
        $cmd->argument('target')->required();
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'deploy'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('required', $err->content());
    }

    public function testEnumConstrainedArgumentRejectsInvalidValue(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('deploy');
        $cmd->argument('env')->required()->enum(TestEnvironment::class);
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'deploy', 'qa'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('qa', $err->content());
    }

    public function testEnumConstrainedArgumentAcceptsValidCaseValue(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('deploy');
        $cmd->argument('env')->required()->enum(TestEnvironment::class);
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->argument('env');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'deploy', 'prod'], $this->console());
        $this->assertSame('prod', $captured);
    }

    public function testOptionsAndPositionalsInterleavedInGnuMode(): void
    {
        $capturedFlag = null;
        $capturedArgs = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$capturedFlag, &$capturedArgs): int {
            $capturedFlag = $i->flag('verbose');
            $capturedArgs = $i->arguments();
            return ExitCode::Success->value;
        });

        // In GNU mode, options may appear after positionals
        $router->compile()->run(['app', 'run', 'file1', '-v', 'file2'], $this->console());

        $this->assertSame(1, $capturedFlag);
        $this->assertSame(['file1', 'file2'], $capturedArgs);
    }
}

enum TestEnvironment: string
{
    case Dev  = 'dev';
    case Prod = 'prod';
}
