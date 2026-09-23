<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationMessageLogCleaner;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMonitor;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

final class DoctrineIntegrationMessageOperationsTest extends TestCase
{
    private Connection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock      = new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00'));
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_outbox ('
            . 'id VARCHAR(36) NOT NULL PRIMARY KEY, '
            . 'created_at TEXT NOT NULL, '
            . 'published_at TEXT DEFAULT NULL, '
            . 'attempts INTEGER NOT NULL DEFAULT 0'
            . ')',
        );
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id VARCHAR(36) NOT NULL, '
            . 'processed_at TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ')',
        );
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_delivery_failure ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id VARCHAR(36) NOT NULL, '
            . 'dead_lettered_at TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ')',
        );
    }

    #[Test]
    public function itDeletesOnlyCompletedMessagesOlderThanTheRetentionPeriod(): void
    {
        $this->insertOutbox('old-published', '2026-08-25 09:59:59.999999', 0, '2026-08-25 09:59:59.999999');
        $this->insertOutbox('new-published', '2026-08-25 10:00:00.000000', 0, '2026-08-25 10:00:00.000000');
        $this->insertOutbox('pending', '2026-08-25 09:59:59.999999', 0, null);
        $this->insertInbox('old-processed', '2026-08-25 09:59:59.999999');
        $this->insertInbox('new-processed', '2026-08-25 10:00:00.000000');
        $this->insertInbox('pending', null);
        $this->insertDeliveryFailure('old-dead-letter', '2026-08-25 09:59:59.999999');
        $this->insertDeliveryFailure('new-dead-letter', '2026-08-25 10:00:00.000000');
        $this->insertDeliveryFailure('retrying', null);
        $cleaner = new DoctrineIntegrationMessageLogCleaner($this->connection, $this->clock);

        $result = $cleaner->clean(30, 100);

        self::assertSame(1, $result->outboxMessages());
        self::assertSame(1, $result->inboxMessages());
        self::assertSame(1, $result->deliveryFailures());
        self::assertSame(2, $this->rowCount('integration_event_outbox'));
        self::assertSame(2, $this->rowCount('integration_event_inbox'));
        self::assertSame(2, $this->rowCount('integration_event_delivery_failure'));
    }

    #[Test]
    public function itReportsPendingAndRetriedOutboxMessages(): void
    {
        $this->insertOutbox('pending', '2000-01-01 00:00:00', 0, null);
        $this->insertOutbox('retried', '2001-01-01 00:00:00', 2, null);
        $this->insertOutbox('published', '1999-01-01 00:00:00', 1, '2000-01-01 00:00:00');
        $monitor = new DoctrineOutboxMonitor($this->connection);

        $status = $monitor->status();

        self::assertSame(2, $status->pendingMessages());
        self::assertSame(1, $status->retriedMessages());
        self::assertSame('2000-01-01 00:00:00', $status->oldestPendingAt());
    }

    #[Test]
    public function itReportsAnEmptyCleanup(): void
    {
        $result = new DoctrineIntegrationMessageLogCleaner($this->connection, $this->clock)->clean(30, 100);

        self::assertSame(0, $result->outboxMessages());
        self::assertSame(0, $result->inboxMessages());
        self::assertSame(0, $result->deliveryFailures());
    }

    #[Test]
    public function itRejectsAnUnsafeRetentionPeriod(): void
    {
        $cleaner = new DoctrineIntegrationMessageLogCleaner($this->connection, $this->clock);

        $this->expectException(InvalidArgumentException::class);

        $cleaner->clean(29, 100);
    }

    private function insertOutbox(string $id, string $createdAt, int $attempts, string|null $publishedAt): void
    {
        $this->connection->insert('integration_event_outbox', [
            'id' => $id,
            'created_at' => $createdAt,
            'published_at' => $publishedAt,
            'attempts' => $attempts,
        ]);
    }

    private function insertInbox(string $messageId, string|null $processedAt): void
    {
        $this->connection->insert('integration_event_inbox', [
            'consumer_name' => 'events',
            'message_id' => $messageId,
            'processed_at' => $processedAt,
        ]);
    }

    private function insertDeliveryFailure(string $messageId, string|null $deadLetteredAt): void
    {
        $this->connection->insert('integration_event_delivery_failure', [
            'consumer_name' => 'events',
            'message_id' => $messageId,
            'dead_lettered_at' => $deadLetteredAt,
        ]);
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
