<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use LogicException;
use Ramsey\Uuid\Uuid;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class DoctrineIntegrationEventOutbox implements IntegrationEventOutbox
{
    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    public function assertTransactionActive(): void
    {
        $connection = $this->connection;
        if ($connection->isTransactionActive()) {
            return;
        }

        throw new LogicException('Integration event dispatch requires an active database transaction.');
    }

    public function append(IntegrationEvent $event): void
    {
        $this->assertTransactionActive();
        $clock      = $this->clock;
        $now        = $clock->now();
        $createdAt  = $now->format('Y-m-d H:i:s.u');
        $occurredOn = $event->occurredOn();
        $identifier = Uuid::uuid7();
        $row        = [
            'id' => $identifier->toString(),
            'event_name' => $event->eventName(),
            'event_version' => $event->eventVersion(),
            'payload' => json_encode($event->getEventArguments(), JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredOn->format('Y-m-d H:i:s.u'),
            'created_at' => $createdAt,
            'available_at' => $createdAt,
            'published_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ];
        $connection = $this->connection;
        $connection->insert('integration_event_outbox', $row);
    }
}
