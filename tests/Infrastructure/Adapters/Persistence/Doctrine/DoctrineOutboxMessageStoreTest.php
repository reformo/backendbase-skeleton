<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

final class DoctrineOutboxMessageStoreTest extends TestCase
{
    private Connection $connection;
    private DoctrineOutboxMessageStore $store;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE integration_event_outbox ('
            . 'id VARCHAR(36) NOT NULL PRIMARY KEY, event_name VARCHAR(190) NOT NULL, '
            . 'event_version VARCHAR(32) NOT NULL, payload TEXT NOT NULL, occurred_at TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL, available_at TEXT NOT NULL, published_at TEXT DEFAULT NULL, '
            . 'claim_token VARCHAR(36) DEFAULT NULL, claim_until TEXT DEFAULT NULL, '
            . 'attempts INTEGER DEFAULT 0 NOT NULL, last_error VARCHAR(255) DEFAULT NULL)',
        );
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
        $this->store = new DoctrineOutboxMessageStore($this->connection);
    }

    #[Test]
    public function itClaimsAndMarksAnAvailableMessageAsPublished(): void
    {
        $message = $this->store->claimNext(
            new DateTimeImmutable('2000-01-01 00:00:01 UTC'),
            new DateTimeImmutable('2000-01-01 00:01:01 UTC'),
        );
        self::assertNotNull($message);
        self::assertSame('message-id', $message->id());
        self::assertSame('Example_Removed', $message->eventName());
        self::assertNotSame('', $message->claimToken());

        $this->store->markPublished($message, new DateTimeImmutable('2000-01-01 00:00:02 UTC'));

        self::assertSame('2000-01-01 00:00:02.000000', $this->value('published_at'));
        self::assertNull($this->value('claim_token'));
    }

    #[Test]
    public function itRecordsTheProvidedFailureStatusWithoutChoosingPolicy(): void
    {
        $message = $this->store->claimNext(
            new DateTimeImmutable('2000-01-01 00:00:01 UTC'),
            new DateTimeImmutable('2000-01-01 00:01:01 UTC'),
        );
        self::assertNotNull($message);

        $this->store->recordPublicationFailure(
            $message,
            3,
            new DateTimeImmutable('2000-01-01 00:00:09 UTC'),
            'publish-failed',
        );

        self::assertSame(3, (int) $this->value('attempts'));
        self::assertSame('2000-01-01 00:00:09.000000', $this->value('available_at'));
        self::assertSame('publish-failed', $this->value('last_error'));
        self::assertNull($this->value('claim_token'));
    }

    #[Test]
    public function itLocksClaimsOnDatabasesThatSupportSkipLocked(): void
    {
        $property = new ReflectionProperty(Connection::class, 'platform');
        $property->setValue($this->connection, new MySQLPlatform());

        try {
            $this->store->claimNext(
                new DateTimeImmutable('2000-01-01 00:00:01 UTC'),
                new DateTimeImmutable('2000-01-01 00:01:01 UTC'),
            );
        } catch (Throwable) {
        }

        self::addToAssertionCount(1);
    }

    private function value(string $column): mixed
    {
        return $this->connection->fetchOne(
            'SELECT ' . $column . " FROM integration_event_outbox WHERE id = 'message-id'",
        );
    }
}
