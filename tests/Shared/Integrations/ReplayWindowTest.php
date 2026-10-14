<?php

declare(strict_types=1);

namespace Tests\Shared\Integrations;

use Backendbase\Shared\Integrations\Messaging\ReplayWindow;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;
use UnexpectedValueException;

final class ReplayWindowTest extends TestCase
{
    #[DataProvider('occurrenceTimes')]
    public function testReplayUsesOriginalUtcOccurrenceWithInclusiveSevenDayCutoff(string|null $occurredAt, bool $eligible): void
    {
        $clock  = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $window = new ReplayWindow($clock);
        if (! $eligible) {
            $this->expectException(UnexpectedValueException::class);
        }

        $window->assertEligible($occurredAt);
        self::assertTrue($eligible);
    }

    /** @return iterable<string, array{string|null, bool}> */
    public static function occurrenceTimes(): iterable
    {
        yield 'now' => ['2026-10-14 10:00:00.000000', true];
        yield 'cutoff' => ['2026-10-07 10:00:00.000000', true];
        yield 'before cutoff' => ['2026-10-07 09:59:59.999999', false];
        yield 'future' => ['2026-10-14 10:00:00.000001', false];
        yield 'missing' => [null, false];
        yield 'date only' => ['2026-10-14', false];
        yield 'overflow' => ['2026-02-30 10:00:00.000000', false];
    }
}
