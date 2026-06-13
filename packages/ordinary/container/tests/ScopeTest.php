<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests;

use Ordinary\Container\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scope::class)]
final class ScopeTest extends TestCase
{
    #[Test]
    public function singletonCaseExists(): void
    {
        $this->assertSame('Singleton', Scope::Singleton->name);
    }

    #[Test]
    public function transientCaseExists(): void
    {
        $this->assertSame('Transient', Scope::Transient->name);
    }

    #[Test]
    public function scopedCaseExists(): void
    {
        $this->assertSame('Scoped', Scope::Scoped->name);
    }

    #[Test]
    public function casesReturnsAllThree(): void
    {
        $this->assertCount(3, Scope::cases());
    }
}
