<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Bootstrap;

use Backendbase\Infrastructure\Adapters\Http\Bootstrap\ConsumerApiTarget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsumerApiTargetTest extends TestCase
{
    #[Test]
    public function itResolvesTheConfiguredSourceId(): void
    {
        $target = ConsumerApiTarget::fromSourceId('example');

        self::assertNotNull($target);
        self::assertSame('ExampleApi', $target->name());
        self::assertSame('example-api', $target->slug());
    }

    #[Test]
    public function itRejectsMissingOrUnknownSourceIds(): void
    {
        self::assertNull(ConsumerApiTarget::fromSourceId(null));
        self::assertNull(ConsumerApiTarget::fromSourceId('unknown'));
    }
}
