<?php

declare(strict_types=1);

namespace Tests\Support\Time;

use Backendbase\Shared\Time\Clock;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $currentTime;

    public function __construct(DateTimeImmutable $currentTime)
    {
        $utcTime           = $currentTime->setTimezone(new DateTimeZone('UTC'));
        $this->currentTime = $utcTime;
    }

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function advance(DateInterval $interval): void
    {
        $currentTime       = $this->currentTime;
        $nextTime          = $currentTime->add($interval);
        $this->currentTime = $nextTime;
    }
}
