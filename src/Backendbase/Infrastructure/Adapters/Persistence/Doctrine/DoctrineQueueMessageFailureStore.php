<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Persistence\QueueMessageFailureStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineQueueMessageFailureStore implements QueueMessageFailureStore
{
    private const string FAILURE_TABLE = 'integration_event_delivery_failure';

    public function __construct(private Connection $connection)
    {
    }

    public function recordFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
        DateTimeImmutable $failedAt,
        bool $deadLettered,
    ): int {
        return $this->connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $failureType,
            $failedAt,
            $deadLettered,
        ): int {
            $attempts = $connection->fetchOne(
                'SELECT attempts FROM ' . self::FAILURE_TABLE
                . ' WHERE consumer_name = :consumerName AND message_id = :messageId',
                ['consumerName' => $consumerName, 'messageId' => $messageId],
            );
            if ($attempts === false) {
                $connection->insert(self::FAILURE_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'attempts' => 1,
                    'last_failure_type' => $failureType,
                    'last_failed_at' => $failedAt->format('Y-m-d H:i:s.u'),
                    'dead_lettered_at' => $deadLettered ? $failedAt->format('Y-m-d H:i:s.u') : null,
                ]);

                return 1;
            }

            $attempts = (int) $attempts + 1;
            $connection->update(
                self::FAILURE_TABLE,
                [
                    'attempts' => $attempts,
                    'last_failure_type' => $failureType,
                    'last_failed_at' => $failedAt->format('Y-m-d H:i:s.u'),
                    'dead_lettered_at' => $deadLettered ? $failedAt->format('Y-m-d H:i:s.u') : null,
                ],
                ['consumer_name' => $consumerName, 'message_id' => $messageId],
            );

            return $attempts;
        });
    }

    public function markDeadLettered(
        string $consumerName,
        string $messageId,
        DateTimeImmutable $deadLetteredAt,
    ): void {
        $this->connection->update(
            self::FAILURE_TABLE,
            ['dead_lettered_at' => $deadLetteredAt->format('Y-m-d H:i:s.u')],
            ['consumer_name' => $consumerName, 'message_id' => $messageId],
        );
    }

    public function clear(string $consumerName, string $messageId): void
    {
        $this->connection->delete(self::FAILURE_TABLE, [
            'consumer_name' => $consumerName,
            'message_id' => $messageId,
        ]);
    }
}
