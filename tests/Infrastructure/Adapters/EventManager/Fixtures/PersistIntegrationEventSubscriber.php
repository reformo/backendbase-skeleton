<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager\Fixtures;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Doctrine\DBAL\Connection;

final readonly class PersistIntegrationEventSubscriber implements IntegrationEventSubscriber
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array<int, string> */
    public static function getSubscribedEvents(): array
    {
        return [EntryRemoved::EVENT_TYPE];
    }

    public function handle(IntegrationEvent $integrationEvent): void
    {
        $connection    = $this->connection;
        $businessCount = $connection->fetchOne('SELECT COUNT(*) FROM aggregate_write');
        $outboxCount   = $connection->fetchOne('SELECT COUNT(*) FROM integration_event_outbox');
        $row           = [
            'event_name' => $integrationEvent->eventName(),
            'business_count' => $businessCount,
            'outbox_count' => $outboxCount,
        ];
        $connection->insert('subscriber_write', $row);
    }
}
