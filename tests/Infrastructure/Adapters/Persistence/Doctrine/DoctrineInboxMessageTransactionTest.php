<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Time\FrozenClock;

final class DoctrineInboxMessageTransactionTest extends TestCase
{
    private Connection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock      = new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00'));
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name VARCHAR(100) NOT NULL, '
            . 'message_id VARCHAR(36) NOT NULL, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'received_at TEXT NOT NULL, '
            . 'processed_at TEXT DEFAULT NULL, '
            . 'PRIMARY KEY (consumer_name, message_id)'
            . ')',
        );
        $this->connection->executeStatement(
            'CREATE TABLE subscriber_write (id VARCHAR(36) NOT NULL PRIMARY KEY)',
        );
    }

    #[Test]
    public function itCommitsTheSubscriberWriteAndInboxRecordOnce(): void
    {
        $transaction = new DoctrineInboxMessageTransaction($this->connection, $this->clock);
        $handler     = function (): void {
            $this->connection->insert('subscriber_write', ['id' => 'write-id']);
            $this->clock->advance(new DateInterval('PT2S'));
        };

        $transaction->processOnce('events', 'message-id', 'Example_Changed_Event', $handler);
        $transaction->processOnce('events', 'message-id', 'Example_Changed_Event', $handler);

        self::assertSame(1, $this->rowCount('subscriber_write'));
        self::assertSame(1, $this->rowCount('integration_event_inbox'));
        self::assertSame('2026-09-24 10:00:02.000000', $this->connection->fetchOne(
            "SELECT processed_at FROM integration_event_inbox WHERE message_id = 'message-id'",
        ));
    }

    #[Test]
    public function itRollsBackTheInboxRecordWhenTheSubscriberFails(): void
    {
        $transaction = new DoctrineInboxMessageTransaction($this->connection, $this->clock);

        try {
            $transaction->processOnce(
                'events',
                'message-id',
                'Example_Changed_Event',
                static function (): void {
                    throw new RuntimeException('Subscriber failed.');
                },
            );
            self::fail('The subscriber failure must escape the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Subscriber failed.', $exception->getMessage());
        }

        self::assertSame(0, $this->rowCount('integration_event_inbox'));
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
