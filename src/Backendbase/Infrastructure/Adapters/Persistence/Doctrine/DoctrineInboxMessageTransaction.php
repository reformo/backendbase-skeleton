<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Persistence\InboxMessageTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineInboxMessageTransaction implements InboxMessageTransaction
{
    private const string INBOX_TABLE = 'integration_event_inbox';

    public function __construct(private Connection $connection)
    {
    }

    public function processOnce(
        string $consumerName,
        string $messageId,
        string $eventName,
        callable $databaseMutation,
    ): void {
        $this->connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $eventName,
            $databaseMutation,
        ): void {
            $receivedAt = DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
            try {
                $connection->insert(self::INBOX_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'event_name' => $eventName,
                    'received_at' => $receivedAt,
                    'processed_at' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            $databaseMutation();
            $connection->update(
                self::INBOX_TABLE,
                ['processed_at' => DateTimeImmutable::create()->format('Y-m-d H:i:s.u')],
                ['consumer_name' => $consumerName, 'message_id' => $messageId],
            );
        });
    }
}
