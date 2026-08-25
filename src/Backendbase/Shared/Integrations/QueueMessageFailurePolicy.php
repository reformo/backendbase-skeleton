<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;

interface QueueMessageFailurePolicy
{
    public function permanentFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome;

    public function transientFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome;

    public function succeeded(string $consumerName, string $messageId): void;
}
