<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use Backendbase\Shared\Time\Clock;

final readonly class QueueMessageFailureService implements QueueMessageFailurePolicy
{
    private const int MAX_ATTEMPTS = 5;

    public function __construct(private QueueMessageFailureStore $failureStore, private Clock $clock)
    {
    }

    public function permanentFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $clock    = $this->clock;
        $failedAt = $clock->now();
        $this->failureStore->recordFailure(
            $consumerName,
            $messageId,
            $failureType,
            $failedAt,
            true,
        );

        return QueueMessageHandlingOutcome::REJECT;
    }

    public function transientFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $clock    = $this->clock;
        $failedAt = $clock->now();
        $attempts = $this->failureStore->recordFailure(
            $consumerName,
            $messageId,
            $failureType,
            $failedAt,
            false,
        );
        if ($attempts < self::MAX_ATTEMPTS) {
            return QueueMessageHandlingOutcome::RETRY;
        }

        $deadLetteredAt = $clock->now();
        $this->failureStore->markDeadLettered(
            $consumerName,
            $messageId,
            $deadLetteredAt,
        );

        return QueueMessageHandlingOutcome::REJECT;
    }

    public function succeeded(string $consumerName, string $messageId): void
    {
        $this->failureStore->clear($consumerName, $messageId);
    }
}
