<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Doctrine\DBAL\Connection;

final readonly class DoctrineQueueMessageFailurePolicy implements QueueMessageFailurePolicy
{
    private const string FAILURE_TABLE = 'integration_event_delivery_failure';
    private const int MAX_ATTEMPTS     = 5;

    public function __construct(private Connection $connection)
    {
    }

    public function permanentFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $this->recordFailure($consumerName, $messageId, $failureType, true);

        return QueueMessageHandlingOutcome::REJECT;
    }

    public function transientFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
    ): QueueMessageHandlingOutcome {
        $attempts = $this->recordFailure($consumerName, $messageId, $failureType, false);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->markDeadLettered($consumerName, $messageId);

            return QueueMessageHandlingOutcome::REJECT;
        }

        return QueueMessageHandlingOutcome::RETRY;
    }

    public function succeeded(string $consumerName, string $messageId): void
    {
        $this->connection->delete(self::FAILURE_TABLE, [
            'consumer_name' => $consumerName,
            'message_id' => $messageId,
        ]);
    }

    private function recordFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
        bool $deadLettered,
    ): int {
        return $this->connection->transactional(static function (Connection $connection) use (
            $consumerName,
            $messageId,
            $failureType,
            $deadLettered,
        ): int {
            $attempts = $connection->fetchOne(
                'SELECT attempts FROM ' . self::FAILURE_TABLE
                . ' WHERE consumer_name = :consumerName AND message_id = :messageId',
                ['consumerName' => $consumerName, 'messageId' => $messageId],
            );
            $now      = DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
            if ($attempts === false) {
                $connection->insert(self::FAILURE_TABLE, [
                    'consumer_name' => $consumerName,
                    'message_id' => $messageId,
                    'attempts' => 1,
                    'last_failure_type' => $failureType,
                    'last_failed_at' => $now,
                    'dead_lettered_at' => $deadLettered ? $now : null,
                ]);

                return 1;
            }

            $attempts = (int) $attempts + 1;
            $connection->update(
                self::FAILURE_TABLE,
                [
                    'attempts' => $attempts,
                    'last_failure_type' => $failureType,
                    'last_failed_at' => $now,
                    'dead_lettered_at' => $deadLettered ? $now : null,
                ],
                ['consumer_name' => $consumerName, 'message_id' => $messageId],
            );

            return $attempts;
        });
    }

    private function markDeadLettered(string $consumerName, string $messageId): void
    {
        $this->connection->update(
            self::FAILURE_TABLE,
            ['dead_lettered_at' => DateTimeImmutable::create()->format('Y-m-d H:i:s.u')],
            ['consumer_name' => $consumerName, 'message_id' => $messageId],
        );
    }
}
