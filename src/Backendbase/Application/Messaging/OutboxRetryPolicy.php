<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use DateTimeImmutable;

use function min;

final class OutboxRetryPolicy
{
    private const int CLAIM_TTL_SECONDS = 60;

    public static function claimUntil(DateTimeImmutable $claimedAt): DateTimeImmutable
    {
        return $claimedAt->modify('+' . self::CLAIM_TTL_SECONDS . ' seconds');
    }

    public static function nextAvailableAt(DateTimeImmutable $failedAt, int $attempts): DateTimeImmutable
    {
        $delay = min(300, 2 ** min($attempts, 8));

        return $failedAt->modify('+' . $delay . ' seconds');
    }
}
