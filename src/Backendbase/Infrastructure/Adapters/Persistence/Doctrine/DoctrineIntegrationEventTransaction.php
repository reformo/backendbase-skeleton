<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class DoctrineIntegrationEventTransaction implements IntegrationEventTransaction
{
    private const string OUTBOX_TABLE = 'integration_event_outbox';

    public function __construct(private Connection $connection)
    {
    }

    /** @param callable(): IntegrationEvent $transactionalWork */
    public function execute(callable $transactionalWork): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($transactionalWork): void {
            $event = $transactionalWork();
            $now   = DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
            $connection->insert(self::OUTBOX_TABLE, [
                'id' => Uuid::uuid7()->toString(),
                'event_name' => $event->eventName(),
                'event_version' => $event->eventVersion(),
                'payload' => json_encode($event->getEventArguments(), JSON_THROW_ON_ERROR),
                'occurred_at' => $event->occurredOn()->format('Y-m-d H:i:s.u'),
                'created_at' => $now,
                'available_at' => $now,
                'published_at' => null,
                'attempts' => 0,
                'last_error' => null,
            ]);
        });
    }
}
