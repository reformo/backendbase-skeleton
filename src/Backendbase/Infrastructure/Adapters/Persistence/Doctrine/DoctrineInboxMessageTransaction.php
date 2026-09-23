<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineInboxMessageTransaction implements InboxMessageTransaction
{
    private const string INBOX_TABLE = 'integration_event_inbox';

    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    public function processOnce(
        string $consumerName,
        string $messageId,
        string $eventName,
        callable $databaseMutation,
    ): void {
        $clock = $this->clock;
        $this->connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $eventName,
            $databaseMutation,
            $clock,
        ): void {
            $receivedAt      = $clock->now();
            $receivedAtValue = $receivedAt->format('Y-m-d H:i:s.u');
            try {
                $connection->insert(self::INBOX_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'event_name' => $eventName,
                    'received_at' => $receivedAtValue,
                    'processed_at' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            $databaseMutation();
            $processedAt      = $clock->now();
            $processedAtValue = $processedAt->format('Y-m-d H:i:s.u');
            $connection->update(
                self::INBOX_TABLE,
                ['processed_at' => $processedAtValue],
                ['consumer_name' => $consumerName, 'message_id' => $messageId],
            );
        });
    }
}
