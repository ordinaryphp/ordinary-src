<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Matcher;

use DateTimeImmutable;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Matcher\GenericCallableMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericCallableMatcher::class)]
final class GenericCallableMatcherTest extends TestCase
{
    #[Test]
    public function it_returns_true_when_closure_returns_true(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());
        $matcher = new GenericCallableMatcher(fn(LogItemInterface $i) => true);

        $this->assertTrue($matcher->matches($item));
    }

    #[Test]
    public function it_returns_false_when_closure_returns_false(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());
        $matcher = new GenericCallableMatcher(fn(LogItemInterface $i) => false);

        $this->assertFalse($matcher->matches($item));
    }

    #[Test]
    public function it_passes_the_log_item_to_the_closure(): void
    {
        $item = new GenericLogItem(LogLevel::Error, 'msg', new DateTimeImmutable());
        $received = null;

        $matcher = new GenericCallableMatcher(function (LogItemInterface $i) use (&$received): bool {
            $received = $i;
            return true;
        });

        $matcher->matches($item);

        $this->assertSame($item, $received);
    }

    #[Test]
    public function it_can_match_on_context_values(): void
    {
        $withKey = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable(), ['user_id' => 42]);
        $withoutKey = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());

        $matcher = new GenericCallableMatcher(
            fn(LogItemInterface $i) => \array_key_exists('user_id', $i->context),
        );

        $this->assertTrue($matcher->matches($withKey));
        $this->assertFalse($matcher->matches($withoutKey));
    }
}
