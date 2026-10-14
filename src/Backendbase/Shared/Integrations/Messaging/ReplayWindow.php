<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Messaging;

use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

final readonly class ReplayWindow
{
    public const int DAYS = 7;

    public function __construct(private Clock $clock)
    {
    }

    public function assertEligible(string|null $occurredAt): void
    {
        if ($occurredAt === null) {
            throw new UnexpectedValueException('The immutable message occurrence time is missing.');
        }

        $occurred = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $occurredAt, new DateTimeZone('UTC'));
        if ($occurred === false || $occurred->format('Y-m-d H:i:s.u') !== $occurredAt) {
            throw new UnexpectedValueException('The message occurrence time is invalid.');
        }

        $clock  = $this->clock;
        $now    = $clock->now();
        $cutoff = $now->modify('-' . self::DAYS . ' days');
        if ($occurred > $now || $occurred < $cutoff) {
            throw new UnexpectedValueException('The message is outside the supported replay window.');
        }
    }
}
