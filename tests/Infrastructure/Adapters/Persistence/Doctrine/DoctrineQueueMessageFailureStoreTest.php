<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailureStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctrineQueueMessageFailureStoreTest extends TestCase
{
    private Connection $connection;
    private DoctrineQueueMessageFailureStore $store;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_delivery_failure ('
            . 'consumer_name VARCHAR(100) NOT NULL, message_id VARCHAR(36) NOT NULL, '
            . 'attempts INTEGER NOT NULL, last_failure_type VARCHAR(190) NOT NULL, '
            . 'last_failed_at TEXT NOT NULL, dead_lettered_at TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id))',
        );
        $this->store = new DoctrineQueueMessageFailureStore($this->connection);
    }

    #[Test]
    public function itRecordsAndUpdatesFailuresWithoutMakingRetryDecisions(): void
    {
        $firstFailure = new DateTimeImmutable('2000-01-01 00:00:01 UTC');
        $lastFailure  = new DateTimeImmutable('2000-01-01 00:00:02 UTC');

        self::assertSame(1, $this->store->recordFailure(
            'events',
            'message-id',
            'temporary',
            $firstFailure,
            false,
        ));
        self::assertSame(2, $this->store->recordFailure(
            'events',
            'message-id',
            'permanent',
            $lastFailure,
            true,
        ));

        $failure = $this->connection->fetchAssociative(
            "SELECT * FROM integration_event_delivery_failure WHERE message_id = 'message-id'",
        );
        self::assertIsArray($failure);
        self::assertSame('permanent', $failure['last_failure_type']);
        self::assertSame('2000-01-01 00:00:02.000000', $failure['dead_lettered_at']);
    }

    #[Test]
    public function itMarksAndClearsFailureStatus(): void
    {
        $this->store->recordFailure(
            'events',
            'message-id',
            'temporary',
            new DateTimeImmutable('2000-01-01 00:00:01 UTC'),
            false,
        );
        $this->store->markDeadLettered(
            'events',
            'message-id',
            new DateTimeImmutable('2000-01-01 00:00:02 UTC'),
        );
        self::assertSame(
            '2000-01-01 00:00:02.000000',
            $this->connection->fetchOne(
                "SELECT dead_lettered_at FROM integration_event_delivery_failure WHERE message_id = 'message-id'",
            ),
        );

        $this->store->clear('events', 'message-id');

        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM integration_event_delivery_failure',
        ));
    }
}
