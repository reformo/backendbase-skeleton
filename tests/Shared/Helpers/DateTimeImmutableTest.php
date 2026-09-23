<?php

declare(strict_types=1);

namespace Tests\Shared\Helpers;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;

final class DateTimeImmutableTest extends TestCase
{
    #[Test]
    public function itDefaultsToUtcAndPreservesExplicitTimeZones(): void
    {
        $previousTimeZone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
        try {
            $now      = DateTimeImmutable::create();
            $utc      = DateTimeImmutable::create('2026-09-24 10:00:00');
            $istanbul = DateTimeImmutable::create('2026-09-24 10:00:00', 'Europe/Istanbul');
            $offset   = DateTimeImmutable::create('2026-09-24T10:00:00+03:00');

            self::assertSame('UTC', $now->format('e'));
            self::assertSame('+00:00', $utc->format('P'));
            self::assertSame('Europe/Istanbul', $istanbul->format('e'));
            self::assertSame('+03:00', $offset->format('P'));
            self::assertSame($istanbul->getTimestamp(), $offset->getTimestamp());
        } finally {
            date_default_timezone_set($previousTimeZone);
        }
    }
}
