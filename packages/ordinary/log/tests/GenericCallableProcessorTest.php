<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use Ordinary\Log\GenericCallableProcessor;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\ImmutableLogItemInterface;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericCallableProcessor::class)]
final class GenericCallableProcessorTest extends TestCase
{
    #[Test]
    public function it_delegates_to_the_closure(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'original', new DateTimeImmutable());
        $called = false;

        $processor = new GenericCallableProcessor(function (ImmutableLogItemInterface $m) use (&$called): ImmutableLogItemInterface {
            $called = true;
            return $m;
        });

        $processor->process($item);

        $this->assertTrue($called);
    }

    #[Test]
    public function it_receives_the_log_item_state(): void
    {
        $dt = new DateTimeImmutable();
        $item = new GenericLogItem(LogLevel::Warning, 'msg', $dt, ['key' => 'value']);
        $seenLevel = null;
        $seenMessage = null;
        $seenContext = null;

        $processor = new GenericCallableProcessor(
            function (ImmutableLogItemInterface $m) use (&$seenLevel, &$seenMessage, &$seenContext): ImmutableLogItemInterface {
                $seenLevel = $m->level;
                $seenMessage = $m->message;
                $seenContext = $m->context;
                return $m;
            },
        );

        $processor->process($item);

        $this->assertSame(LogLevel::Warning, $seenLevel);
        $this->assertSame('msg', $seenMessage);
        $this->assertSame(['key' => 'value'], $seenContext);
    }

    #[Test]
    public function it_can_enrich_context(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor = new GenericCallableProcessor(
            fn(ImmutableLogItemInterface $i): ImmutableLogItemInterface => $i->withContext(['enriched' => true]),
        );

        $result = $processor->process($item);

        $this->assertTrue($result->context['enriched']);
        $this->assertArrayNotHasKey('enriched', $item->context);
    }

    #[Test]
    public function it_reflects_closure_transformations(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'original', new DateTimeImmutable());

        $processor = new GenericCallableProcessor(
            fn(ImmutableLogItemInterface $i): ImmutableLogItemInterface => $i->withLevel(LogLevel::Error)->withMessage('replaced'),
        );

        $result = $processor->process($item);

        $this->assertInstanceOf(LogItemInterface::class, $result);
        $this->assertSame(LogLevel::Error, $result->level);
        $this->assertSame('replaced', $result->message);
        $this->assertSame(LogLevel::Info, $item->level);
        $this->assertSame('original', $item->message);
    }
}
