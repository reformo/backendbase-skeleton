<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;

interface IntegrationEventTransaction
{
    /**
     * Run the command's transactional work and outbox write in one transaction.
     *
     * The work can include database mutations and synchronous domain listeners.
     * It must not perform business-relevant network, process, or filesystem input and output.
     *
     * @param callable(): void $transactionalWork
     */
    public function execute(IntegrationEvent $event, callable $transactionalWork): void;
}
