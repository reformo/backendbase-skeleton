<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

final readonly class IntegrationMessageLogCleanupResult
{
    public function __construct(
        private int $outboxMessages,
        private int $inboxMessages,
        private int $deliveryFailures,
    ) {
    }

    public function outboxMessages(): int
    {
        return $this->outboxMessages;
    }

    public function inboxMessages(): int
    {
        return $this->inboxMessages;
    }

    public function deliveryFailures(): int
    {
        return $this->deliveryFailures;
    }
}
