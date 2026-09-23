<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use Doctrine\DBAL\Connection;

final readonly class DoctrineIntegrationEventTransaction implements IntegrationEventTransaction
{
    public function __construct(
        private Connection $connection,
        private EventManager $eventManager,
    ) {
    }

    /** @param callable(): IntegrationEvent $transactionalWork */
    public function execute(callable $transactionalWork): void
    {
        $eventManager = $this->eventManager;
        $connection   = $this->connection;
        $connection->transactional(static function () use ($transactionalWork, $eventManager): void {
            $event = $transactionalWork();
            $eventManager->dispatchEvent($event);
        });
    }
}
