<?php

declare(strict_types=1);

namespace Ordinary\Cli\Tests\Parser;

use Ordinary\Cli\CommandRouter;
use Ordinary\Cli\Console;
use Ordinary\Cli\ExitCode;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Io\BufferedInput;
use Ordinary\Cli\Io\BufferedOutput;
use Ordinary\Cli\Option\OptionRepeat;
use PHPUnit\Framework\TestCase;

final class ValueOptionParsingTest extends TestCase
{
    private function console(BufferedOutput $out = new BufferedOutput(), BufferedOutput $err = new BufferedOutput()): Console
    {
        return new Console(new BufferedInput(), $out, $err);
    }

    public function testLongValueOptionAcceptsSpaceSeparatedSyntax(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('config');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '--config', '/etc/app.conf'], $this->console());
        $this->assertSame('/etc/app.conf', $captured);
    }

    public function testShortValueOptionAcceptsSpaceSeparatedSyntax(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('config');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '-c', '/etc/app.conf'], $this->console());
        $this->assertSame('/etc/app.conf', $captured);
    }

    public function testEqualsSyntaxIsRejectedAsUnknownOption(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value();
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'run', '--config=/etc/app.conf'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('Unknown option', $err->content());
    }

    public function testDefaultValueReturnedWhenOptionAbsent(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value()->default('/etc/default.conf');
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('config');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run'], $this->console());
        $this->assertSame('/etc/default.conf', $captured);
    }

    public function testNullReturnedWhenOptionAbsentAndNoDefault(): void
    {
        $captured = 'sentinel';
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('config');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run'], $this->console());
        $this->assertNull($captured);
    }

    public function testValueOptionWithStoreLastKeepsLastOccurrence(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('env', 'e')->value()->repeat(OptionRepeat::StoreLast);
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('env');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '--env', 'staging', '--env', 'prod'], $this->console());
        $this->assertSame('prod', $captured);
    }

    public function testValueOptionWithStoreFirstIgnoresSubsequentOccurrences(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('env', 'e')->value()->repeat(OptionRepeat::StoreFirst);
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->value('env');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '--env', 'staging', '--env', 'prod'], $this->console());
        $this->assertSame('staging', $captured);
    }

    public function testValueOptionWithStoreListCollectsAllOccurrences(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('file', 'f')->value()->repeat(OptionRepeat::StoreList);
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->values('file');
            return ExitCode::Success->value;
        });

        $router->compile()->run(['app', 'run', '--file', 'a.txt', '--file', 'b.txt', '-f', 'c.txt'], $this->console());
        $this->assertSame(['a.txt', 'b.txt', 'c.txt'], $captured);
    }

    public function testMissingValueTokenWritesToErrorAndReturnsExitCodeTwo(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('config', 'c')->value();
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'run', '--config'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('requires a value', $err->content());
    }

    public function testUnknownOptionWritesToErrorAndReturnsExitCodeTwo(): void
    {
        $router = new CommandRouter(name: 'app');
        $router->command('run')
            ->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        $err = new BufferedOutput();
        $exitCode = $router->compile()->run(['app', 'run', '--unknown'], $this->console(err: $err));

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('Unknown option', $err->content());
    }
}
