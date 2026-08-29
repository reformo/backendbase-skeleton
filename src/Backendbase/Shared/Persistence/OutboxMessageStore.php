<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use DateTimeImmutable;

interface OutboxMessageStore
{
    public function claimNext(DateTimeImmutable $availableAt, DateTimeImmutable $claimUntil): ClaimedOutboxMessage|null;

    public function markPublished(ClaimedOutboxMessage $message, DateTimeImmutable $publishedAt): void;

    public function recordPublicationFailure(
        ClaimedOutboxMessage $message,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $failureType,
    ): void;
}
