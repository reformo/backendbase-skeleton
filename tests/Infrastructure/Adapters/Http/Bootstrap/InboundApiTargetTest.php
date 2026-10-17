<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Bootstrap;

use Backendbase\Infrastructure\Adapters\Http\Bootstrap\InboundApiTarget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InboundApiTargetTest extends TestCase
{
    #[Test]
    public function itResolvesTheConfiguredSourceId(): void
    {
        $target = InboundApiTarget::fromSourceId('example');

        self::assertNotNull($target);
        self::assertSame('ExampleApi', $target->name());
        self::assertSame('example-api', $target->slug());
    }

    #[Test]
    public function itRejectsMissingOrUnknownSourceIds(): void
    {
        self::assertNull(InboundApiTarget::fromSourceId(null));
        self::assertNull(InboundApiTarget::fromSourceId('unknown'));
    }
}
