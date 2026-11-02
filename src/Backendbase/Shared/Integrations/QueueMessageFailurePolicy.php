<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;

interface QueueMessageFailurePolicy
{
    /** Record rejection and return the application outcome. Broker settlement remains separate. */
    public function permanentFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome;

    /** Record an attempt and return retry below the limit or rejection at the limit. */
    public function transientFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome;

    /** Remove the failure record after successful processing. */
    public function succeeded(string $consumerName, string $messageId): void;
}
