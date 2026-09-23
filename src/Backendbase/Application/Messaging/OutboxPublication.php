<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Time\Clock;

final readonly class OutboxPublication
{
    public function __construct(private OutboxMessageStore $messageStore, private Clock $clock)
    {
    }

    public function claim(): ClaimedOutboxMessage|null
    {
        $clock     = $this->clock;
        $claimedAt = $clock->now();

        $messageStore = $this->messageStore;

        return $messageStore->claimNext($claimedAt, OutboxRetryPolicy::claimUntil($claimedAt));
    }

    public function markPublished(ClaimedOutboxMessage $message): void
    {
        $clock        = $this->clock;
        $publishedAt  = $clock->now();
        $messageStore = $this->messageStore;
        $messageStore->markPublished($message, $publishedAt);
    }

    public function recordFailure(ClaimedOutboxMessage $message): void
    {
        $attempts     = $message->attempts() + 1;
        $clock        = $this->clock;
        $failedAt     = $clock->now();
        $messageStore = $this->messageStore;
        $messageStore->recordPublicationFailure(
            $message,
            $attempts,
            OutboxRetryPolicy::nextAvailableAt($failedAt, $attempts),
            'publish-failed',
        );
    }
}
