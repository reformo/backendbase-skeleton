<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Time;

use Backendbase\Infrastructure\Adapters\Time\SystemClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function time;

final class SystemClockTest extends TestCase
{
    #[Test]
    public function itReadsTheCurrentInstantInUtcRegardlessOfTheProcessTimeZone(): void
    {
        $previousTimeZone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
        try {
            $before = time();
            $clock  = new SystemClock();
            $now    = $clock->now();
            $after  = time();

            self::assertSame('UTC', $now->format('e'));
            self::assertGreaterThanOrEqual($before, $now->getTimestamp());
            self::assertLessThanOrEqual($after, $now->getTimestamp());
        } finally {
            date_default_timezone_set($previousTimeZone);
        }
    }
}
