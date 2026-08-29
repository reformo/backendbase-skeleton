<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\QueueMessageFailureStore;

final readonly class QueueMessageFailureService implements QueueMessageFailurePolicy
{
    private const int MAX_ATTEMPTS = 5;

    public function __construct(private QueueMessageFailureStore $failureStore)
    {
    }

    public function permanentFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $this->failureStore->recordFailure(
            $consumerName,
            $messageId,
            $failureType,
            DateTimeImmutable::create(),
            true,
        );

        return QueueMessageHandlingOutcome::REJECT;
    }

    public function transientFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $attempts = $this->failureStore->recordFailure(
            $consumerName,
            $messageId,
            $failureType,
            DateTimeImmutable::create(),
            false,
        );
        if ($attempts < self::MAX_ATTEMPTS) {
            return QueueMessageHandlingOutcome::RETRY;
        }

        $this->failureStore->markDeadLettered(
            $consumerName,
            $messageId,
            DateTimeImmutable::create(),
        );

        return QueueMessageHandlingOutcome::REJECT;
    }

    public function succeeded(string $consumerName, string $messageId): void
    {
        $this->failureStore->clear($consumerName, $messageId);
    }
}
