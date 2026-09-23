<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Time;

use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
