<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Backendbase\Shared\Integrations\Operation\IntegrationMessageLogCleanupResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

final readonly class DoctrineIntegrationMessageLogCleaner implements IntegrationMessageLogCleaner
{
    private const int MIN_RETENTION_DAYS = 30;

    public function __construct(private Connection $connection)
    {
    }

    public function clean(int $retentionDays, int $limit): IntegrationMessageLogCleanupResult
    {
        if ($retentionDays < self::MIN_RETENTION_DAYS) {
            throw new InvalidArgumentException('Integration message retention must be at least 30 days.');
        }

        $cutoff = DateTimeImmutable::create()
            ->modify('-' . $retentionDays . ' days')
            ->format('Y-m-d H:i:s.u');

        return new IntegrationMessageLogCleanupResult(
            $this->cleanOutbox($cutoff, $limit),
            $this->cleanInbox($cutoff, $limit),
            $this->cleanDeliveryFailures($cutoff, $limit),
        );
    }

    private function cleanOutbox(string $cutoff, int $limit): int
    {
        $messageIds = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('integration_event_outbox')
            ->where('published_at < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('published_at', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();
        if ($messageIds === []) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            'DELETE FROM integration_event_outbox WHERE id IN (:messageIds)',
            ['messageIds' => $messageIds],
            ['messageIds' => ArrayParameterType::STRING],
        );
    }

    private function cleanInbox(string $cutoff, int $limit): int
    {
        $messages = $this->connection->createQueryBuilder()
            ->select('consumer_name', 'message_id')
            ->from('integration_event_inbox')
            ->where('processed_at < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('processed_at', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->connection->transactional(static function (Connection $connection) use ($messages): int {
            $deleted = 0;
            foreach ($messages as $message) {
                $deleted += (int) $connection->delete('integration_event_inbox', [
                    'consumer_name' => $message['consumer_name'],
                    'message_id' => $message['message_id'],
                ]);
            }

            return $deleted;
        });
    }

    private function cleanDeliveryFailures(string $cutoff, int $limit): int
    {
        $messages = $this->connection->createQueryBuilder()
            ->select('consumer_name', 'message_id')
            ->from('integration_event_delivery_failure')
            ->where('dead_lettered_at < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('dead_lettered_at', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->connection->transactional(static function (Connection $connection) use ($messages): int {
            $deleted = 0;
            foreach ($messages as $message) {
                $deleted += (int) $connection->delete('integration_event_delivery_failure', [
                    'consumer_name' => $message['consumer_name'],
                    'message_id' => $message['message_id'],
                ]);
            }

            return $deleted;
        });
    }
}
