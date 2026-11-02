<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Messaging;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineQueueMessageFailureStore;
use Backendbase\Infrastructure\Messaging\QueueMessageFailureService;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class QueueFailureStateTest extends TestCase
{
    private Connection $connection;
    private QueueMessageFailureService $service;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_delivery_failure (consumer_name TEXT, message_id TEXT, attempts INTEGER NOT NULL, last_failure_type TEXT NOT NULL, last_failed_at TEXT NOT NULL, dead_lettered_at TEXT, PRIMARY KEY (consumer_name, message_id))');
        $store            = new DoctrineQueueMessageFailureStore($connection);
        $clock            = new FrozenClock(new DateTimeImmutable('2026-10-14 10:00:00 UTC'));
        $this->service    = new QueueMessageFailureService($store, $clock);
        $this->connection = $connection;
    }

    public function testPermanentRejectionCanBeFollowedByTransientRedeliveryAndSuccess(): void
    {
        $service = $this->service;
        $first   = $service->permanentFailure('events', 'message-id', 'invalid');
        self::assertSame(QueueMessageHandlingOutcome::REJECT, $first);
        $connection = $this->connection;
        self::assertSame('2026-10-14 10:00:00.000000', $connection->fetchOne('SELECT dead_lettered_at FROM integration_event_delivery_failure'));
        $retry = $service->transientFailure('events', 'message-id', 'temporary');
        self::assertSame(QueueMessageHandlingOutcome::RETRY, $retry);
        $row = $connection->fetchAssociative('SELECT attempts, dead_lettered_at FROM integration_event_delivery_failure');
        self::assertIsArray($row);
        self::assertSame(2, (int) $row['attempts']);
        self::assertNull($row['dead_lettered_at']);
        $service->succeeded('events', 'message-id');
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM integration_event_delivery_failure'));
    }

    public function testTransientAttemptsRejectAtTheFifthFailure(): void
    {
        $service = $this->service;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $outcome  = $service->transientFailure('events', 'message-id', 'temporary');
            $expected = $attempt < 5 ? QueueMessageHandlingOutcome::RETRY : QueueMessageHandlingOutcome::REJECT;
            self::assertSame($expected, $outcome);
        }

        $connection = $this->connection;
        $row        = $connection->fetchAssociative('SELECT attempts, dead_lettered_at FROM integration_event_delivery_failure');
        self::assertIsArray($row);
        self::assertSame(5, (int) $row['attempts']);
        self::assertSame('2026-10-14 10:00:00.000000', $row['dead_lettered_at']);
    }
}
