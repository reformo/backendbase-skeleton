<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleRemoved;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationEventTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class DoctrineIntegrationEventTransactionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE aggregate_write (id VARCHAR(36) NOT NULL PRIMARY KEY)');
    }

    #[Test]
    public function itCommitsTheMutationAndOutboxMessageTogether(): void
    {
        $this->createOutboxTable();
        $event       = new ExampleRemoved('example-id');
        $transaction = new DoctrineIntegrationEventTransaction($this->connection);

        $transaction->execute(
            $event,
            function (): void {
                $this->connection->insert('aggregate_write', ['id' => 'example-id']);
            },
        );

        self::assertSame(1, $this->rowCount('aggregate_write'));
        self::assertSame(1, $this->rowCount('integration_event_outbox'));
        $message = $this->connection->fetchAssociative(
            'SELECT event_name, event_version, payload FROM integration_event_outbox',
        );
        self::assertIsArray($message);
        self::assertSame(ExampleRemoved::EVENT_TYPE, $message['event_name']);
        self::assertSame(ExampleRemoved::EVENT_VERSION, $message['event_version']);
        $payload = json_decode((string) $message['payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['exampleId' => 'example-id'], $payload);
    }

    #[Test]
    public function itRollsBackTheMutationWhenTheOutboxWriteFails(): void
    {
        $transaction = new DoctrineIntegrationEventTransaction($this->connection);

        try {
            $transaction->execute(
                new ExampleRemoved('example-id'),
                function (): void {
                    $this->connection->insert('aggregate_write', ['id' => 'example-id']);
                },
            );
            self::fail('The missing outbox table must fail the transaction.');
        } catch (TableNotFoundException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        self::assertSame(0, $this->rowCount('aggregate_write'));
    }

    #[Test]
    public function itRollsBackTheMutationWhenTransactionalWorkFails(): void
    {
        $this->createOutboxTable();
        $transaction = new DoctrineIntegrationEventTransaction($this->connection);

        try {
            $transaction->execute(
                new ExampleRemoved('example-id'),
                function (): void {
                    $this->connection->insert('aggregate_write', ['id' => 'example-id']);

                    throw new RuntimeException('The synchronous listener failed.');
                },
            );
            self::fail('Failed transactional work must fail the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('The synchronous listener failed.', $exception->getMessage());
        }

        self::assertSame(0, $this->rowCount('aggregate_write'));
        self::assertSame(0, $this->rowCount('integration_event_outbox'));
    }

    private function createOutboxTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_outbox ('
            . 'id VARCHAR(36) NOT NULL PRIMARY KEY, '
            . 'event_name VARCHAR(190) NOT NULL, '
            . 'event_version VARCHAR(32) NOT NULL, '
            . 'payload TEXT NOT NULL, '
            . 'occurred_at TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL, '
            . 'available_at TEXT NOT NULL, '
            . 'published_at TEXT DEFAULT NULL, '
            . 'attempts INTEGER DEFAULT 0 NOT NULL, '
            . 'last_error VARCHAR(255) DEFAULT NULL'
            . ')',
        );
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
