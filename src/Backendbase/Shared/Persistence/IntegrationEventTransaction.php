<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;

interface IntegrationEventTransaction
{
    /**
     * Run business work, local integration subscribers, and optional outbox publication in one transaction.
     *
     * The work can include database mutations and synchronous domain listeners.
     * Its returned event is dispatched before commit. DELIVER_VIA_QUEUE controls the outbox insert.
     * Local integration subscribers run for both flag values and must use the same database connection.
     * It must not perform business-relevant network, process, or filesystem input and output.
     *
     * @param callable(): IntegrationEvent $transactionalWork
     */
    public function execute(callable $transactionalWork): void;
}
