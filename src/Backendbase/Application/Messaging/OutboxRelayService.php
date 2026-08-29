<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use Backendbase\Shared\Persistence\OutboxMessageStore;

final readonly class OutboxRelayService implements OutboxRelay
{
    public function __construct(
        private OutboxMessageStore $messageStore,
        private OutboxPublisher $publisher,
    ) {
    }

    public function relay(int $limit): OutboxRelayResult
    {
        $published = 0;
        $failed    = 0;
        for ($processed = 0; $processed < $limit; $processed++) {
            $result = $this->processNext();
            if ($result === null) {
                break;
            }

            $published += $result ? 1 : 0;
            $failed    += $result ? 0 : 1;
        }

        return new OutboxRelayResult($published, $failed);
    }

    private function processNext(): bool|null
    {
        $claimedAt = DateTimeImmutable::create();
        $message   = $this->messageStore->claimNext(
            $claimedAt,
            OutboxRetryPolicy::claimUntil($claimedAt),
        );
        if ($message === null) {
            return null;
        }

        if ($this->publisher->publish($message)->isSuccessful()) {
            $this->messageStore->markPublished($message, DateTimeImmutable::create());

            return true;
        }

        $this->recordFailure($message);

        return false;
    }

    private function recordFailure(ClaimedOutboxMessage $message): void
    {
        $attempts = $message->attempts() + 1;
        $failedAt = DateTimeImmutable::create();
        $this->messageStore->recordPublicationFailure(
            $message,
            $attempts,
            OutboxRetryPolicy::nextAvailableAt($failedAt, $attempts),
            'publish-failed',
        );
    }
}
