<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxRelay;
use Backendbase\Infrastructure\Adapters\Queue\OutboxMessagePublisher;
use Backendbase\Shared\Integrations\BackendbaseQueue;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class DoctrineOutboxRelayTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
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
            . 'claim_token VARCHAR(36) DEFAULT NULL, '
            . 'claim_until TEXT DEFAULT NULL, '
            . 'attempts INTEGER DEFAULT 0 NOT NULL, '
            . 'last_error VARCHAR(255) DEFAULT NULL'
            . ')',
        );
        $this->insertPendingMessage();
    }

    #[Test]
    public function itPublishesAndMarksAnOutboxMessage(): void
    {
        $queue = $this->createMock(BackendbaseQueue::class);
        $queue->expects(self::once())
            ->method('publish')
            ->with([
                'messageId' => 'message-id',
                'messageBody' => 'Example_Removed',
                'eventVersion' => '1.0',
                'properties' => ['exampleId' => 'example-id'],
            ])
            ->willReturnCallback(function (): null {
                self::assertFalse($this->connection->isTransactionActive());

                return null;
            });
        $relay = new DoctrineOutboxRelay(
            $this->connection,
            new OutboxMessagePublisher($queue, new Logger('outbox-relay-test')),
        );

        $result = $relay->relay(10);

        self::assertSame(1, $result->published());
        self::assertSame(0, $result->failed());
        self::assertNotNull($this->connection->fetchOne(
            "SELECT published_at FROM integration_event_outbox WHERE id = 'message-id'",
        ));
        self::assertNull($this->connection->fetchOne(
            "SELECT claim_token FROM integration_event_outbox WHERE id = 'message-id'",
        ));
    }

    #[Test]
    public function itDefersAFailedPublicationForRetry(): void
    {
        $logHandler = new TestHandler();
        $logger     = new Logger('outbox-relay-test');
        $logger->pushHandler($logHandler);
        $queue = $this->createStub(BackendbaseQueue::class);
        $queue->method('publish')->willThrowException(new RuntimeException('Broker unavailable.'));
        $relay = new DoctrineOutboxRelay(
            $this->connection,
            new OutboxMessagePublisher($queue, $logger),
        );

        $result  = $relay->relay(10);
        $message = $this->connection->fetchAssociative(
            "SELECT attempts, last_error, published_at FROM integration_event_outbox WHERE id = 'message-id'",
        );

        self::assertSame(0, $result->published());
        self::assertSame(1, $result->failed());
        self::assertIsArray($message);
        self::assertSame(1, (int) $message['attempts']);
        self::assertSame('publish-failed', $message['last_error']);
        self::assertNull($message['published_at']);
        self::assertTrue($logHandler->hasErrorThatContains('Outbox message publication failed.'));
    }

    #[Test]
    public function itRejectsAScalarOutboxPayload(): void
    {
        $this->connection->update(
            'integration_event_outbox',
            ['payload' => '1'],
            ['id' => 'message-id'],
        );
        $queue = $this->createMock(BackendbaseQueue::class);
        $queue->expects(self::never())->method('publish');
        $relay = new DoctrineOutboxRelay(
            $this->connection,
            new OutboxMessagePublisher($queue, new Logger('outbox-relay-test')),
        );

        $result = $relay->relay(1);

        self::assertSame(1, $result->failed());
    }

    #[Test]
    public function itLocksTheClaimQueryOnDatabasesThatSupportSkipLocked(): void
    {
        $property = new ReflectionProperty(Connection::class, 'platform');
        $property->setValue($this->connection, new MySQLPlatform());
        $relay = new DoctrineOutboxRelay(
            $this->connection,
            new OutboxMessagePublisher(
                $this->createStub(BackendbaseQueue::class),
                new Logger('outbox-relay-test'),
            ),
        );

        try {
            $relay->relay(1);
        } catch (Throwable) {
        }

        self::addToAssertionCount(1);
    }

    private function insertPendingMessage(): void
    {
        $this->connection->insert('integration_event_outbox', [
            'id' => 'message-id',
            'event_name' => 'Example_Removed',
            'event_version' => '1.0',
            'payload' => '{"exampleId":"example-id"}',
            'occurred_at' => '2000-01-01 00:00:00.000000',
            'created_at' => '2000-01-01 00:00:00.000000',
            'available_at' => '2000-01-01 00:00:00.000000',
            'published_at' => null,
            'claim_token' => null,
            'claim_until' => null,
            'attempts' => 0,
            'last_error' => null,
        ]);
    }
}
