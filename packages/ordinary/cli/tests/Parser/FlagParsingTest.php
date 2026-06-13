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

final class FlagParsingTest extends TestCase
{
    /** @return array{Console, BufferedOutput, BufferedOutput} */
    private function console(): array
    {
        $out = new BufferedOutput();
        $err = new BufferedOutput();
        return [new Console(new BufferedInput(), $out, $err), $out, $err];
    }

    public function testShortFlagRecordedAsTrueInParsedInput(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->flag('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '-v'], $console);

        $this->assertSame(1, $captured);
    }

    public function testFlagReturnsZeroWhenNotProvided(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->flag('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run'], $console);

        $this->assertSame(0, $captured);
    }

    public function testShortOptionStackingExpandsEachCharToItsOwnFlag(): void
    {
        $capturedV = null;
        $capturedD = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->option('debug', 'd')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$capturedV, &$capturedD): int {
            $capturedV = $i->flag('verbose');
            $capturedD = $i->flag('debug');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '-vd'], $console);

        $this->assertSame(1, $capturedV);
        $this->assertSame(1, $capturedD);
    }

    public function testFlagReturnsOccurrenceCountFromStackedShortOptions(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->flag('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '-vvv'], $console);

        $this->assertSame(3, $captured);
    }

    public function testFlagReturnsOccurrenceCountFromRepeatedLongOptions(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->flag('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '--verbose', '--verbose'], $console);

        $this->assertSame(2, $captured);
    }

    public function testLongFlagSetsCountToOne(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('dry-run', 'd')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->flag('dry-run');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '--dry-run'], $console);

        $this->assertSame(1, $captured);
    }

    public function testHasReturnsTrueForProvidedFlag(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->has('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run', '-v'], $console);

        $this->assertTrue($captured);
    }

    public function testHasReturnsFalseForAbsentFlag(): void
    {
        $captured = null;
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->handler(function (ParsedInputInterface $i, Console $c) use (&$captured): int {
            $captured = $i->has('verbose');
            return ExitCode::Success->value;
        });

        [$console] = $this->console();
        $router->compile()->run(['app', 'run'], $console);

        $this->assertFalse($captured);
    }

    public function testStackingValueOptionWritesToErrorAndReturnsExitCodeTwo(): void
    {
        $router = new CommandRouter(name: 'app');
        $cmd = $router->command('run');
        $cmd->option('verbose', 'v')->flag();
        $cmd->option('config', 'c')->value();
        $cmd->handler(fn(ParsedInputInterface $i, Console $c): int => ExitCode::Success->value);

        [, , $err] = $this->console();
        $console = new Console(new BufferedInput(), new BufferedOutput(), $err);
        $exitCode = $router->compile()->run(['app', 'run', '-vc', '/path'], $console);

        $this->assertSame(ExitCode::Usage->value, $exitCode);
        $this->assertStringContainsString('stack', $err->content());
    }
}
