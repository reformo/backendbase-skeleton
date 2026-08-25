<?php

declare(strict_types=1);

namespace Tests\Shared\Services\EventManager;

use Backendbase\Shared\Services\EventManager\EventArguments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EventArgumentsTest extends TestCase
{
    #[Test]
    public function itReturnsAReusableEmptyArgumentSet(): void
    {
        $arguments = EventArguments::getEmptyInstance();
        $concrete  = new EventArguments();

        self::assertSame($arguments, EventArguments::getEmptyInstance());
        self::assertSame([], $concrete->toArray());
        self::assertSame('', $arguments->eventName());
        self::assertInstanceOf(stdClass::class, $arguments->get());
    }
}
