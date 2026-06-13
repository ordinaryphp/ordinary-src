<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Attribute;

use Ordinary\Container\Attribute\Inject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inject::class)]
final class InjectTest extends TestCase
{
    #[Test]
    public function storesId(): void
    {
        $inject = new Inject('some.service');

        $this->assertSame('some.service', $inject->id);
    }

    #[Test]
    public function isReadonlyAndFinal(): void
    {
        $r = new \ReflectionClass(Inject::class);

        $this->assertTrue($r->isFinal());
        $this->assertTrue($r->isReadOnly());
    }

    #[Test]
    public function hasTargetParameterAttribute(): void
    {
        $r = new \ReflectionClass(Inject::class);
        $attrs = $r->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);

        /** @var \Attribute $attr */
        $attr = $attrs[0]->newInstance();

        $this->assertSame(\Attribute::TARGET_PARAMETER, $attr->flags);
    }
}
