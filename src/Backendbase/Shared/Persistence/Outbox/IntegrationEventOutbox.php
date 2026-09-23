<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence\Outbox;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;

interface IntegrationEventOutbox
{
    /** Require the transaction shared by business work and local subscribers. */
    public function assertTransactionActive(): void;

    /** Append to the active transaction without publishing to the broker. */
    public function append(IntegrationEvent $event): void;
}
