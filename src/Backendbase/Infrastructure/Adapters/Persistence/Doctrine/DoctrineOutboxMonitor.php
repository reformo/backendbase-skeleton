<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Integrations\OutboxMonitor;
use Backendbase\Shared\Integrations\OutboxStatus;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOutboxMonitor implements OutboxMonitor
{
    public function __construct(private Connection $connection)
    {
    }

    public function status(): OutboxStatus
    {
        $status = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS pending_messages, '
            . 'SUM(CASE WHEN attempts > 0 THEN 1 ELSE 0 END) AS retried_messages, '
            . 'MIN(created_at) AS oldest_pending_at '
            . 'FROM integration_event_outbox WHERE published_at IS NULL',
        );

        return new OutboxStatus(
            (int) ($status['pending_messages'] ?? 0),
            (int) ($status['retried_messages'] ?? 0),
            isset($status['oldest_pending_at']) ? (string) $status['oldest_pending_at'] : null,
        );
    }
}
