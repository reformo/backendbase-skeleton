<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use DateTimeImmutable;

interface OutboxMessageStore
{
    /** Claim one available unpublished message whose earlier claim has expired or is absent. */
    public function claimNext(DateTimeImmutable $availableAt, DateTimeImmutable $claimExpiresAt): ClaimedOutboxMessage|null;

    /** Mark publication only while the stored claim token matches. Expiry alone does not clear the token. */
    public function markPublished(ClaimedOutboxMessage $message, DateTimeImmutable $publishedAt): void;

    /** Record failure only while the stored claim token matches. A stale update has no effect. */
    public function recordPublicationFailure(
        ClaimedOutboxMessage $message,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $failureType,
    ): void;
}
