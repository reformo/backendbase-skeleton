<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Bootstrap;

use Backendbase\Shared\Http\Bootstrap\UseCaseTarget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UseCaseTargetTest extends TestCase
{
    #[Test]
    public function itResolvesTheConfiguredSourceId(): void
    {
        $target = UseCaseTarget::fromSourceId('example');

        self::assertNotNull($target);
        self::assertSame('ExampleApi', $target->name());
        self::assertSame('example-api', $target->slug());
    }

    #[Test]
    public function itRejectsMissingOrUnknownSourceIds(): void
    {
        self::assertNull(UseCaseTarget::fromSourceId(null));
        self::assertNull(UseCaseTarget::fromSourceId('unknown'));
    }
}
