<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class OutboxClaimStateTest extends TestCase
{
    private Connection $connection;
    private DoctrineOutboxMessageStore $store;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_outbox (id TEXT PRIMARY KEY, event_name TEXT, event_version TEXT, payload TEXT, occurred_at TEXT, destination TEXT, created_at TEXT, available_at TEXT, published_at TEXT, claim_token TEXT, claim_expires_at TEXT, attempts INTEGER, last_failure_type TEXT)');
        $connection->insert('integration_event_outbox', [
            'id' => 'message-id',
            'event_name' => 'Example_Changed',
            'event_version' => '1.0',
            'payload' => '{}',
            'occurred_at' => '2026-10-14 10:00:00.000000',
            'created_at' => '2026-10-14 10:00:00.000000',
            'available_at' => '2026-10-14 10:00:00.000000',
            'attempts' => 0,
        ]);
        $this->connection = $connection;
        $this->store      = new DoctrineOutboxMessageStore($connection);
    }

    public function testExpiredClaimCanCompleteUntilAnotherWorkerReplacesItsToken(): void
    {
        $store   = $this->store;
        $started = new DateTimeImmutable('2026-10-14 10:00:00 UTC');
        $expiry  = $started->modify('+60 seconds');
        $claim   = $store->claimNext($started, $expiry);
        self::assertNotNull($claim);
        $before = $expiry->modify('-1 microsecond');
        $later  = $expiry->modify('+60 seconds');
        self::assertNull($store->claimNext($before, $later));
        $store->markPublished($claim, $later);
        $connection = $this->connection;
        self::assertSame('2026-10-14 10:02:00.000000', $connection->fetchOne('SELECT published_at FROM integration_event_outbox'));
    }

    public function testReplacementAtExpiryFencesBothStaleUpdates(): void
    {
        $store   = $this->store;
        $started = new DateTimeImmutable('2026-10-14 10:00:00 UTC');
        $expiry  = $started->modify('+60 seconds');
        $later   = $expiry->modify('+60 seconds');
        $first   = $store->claimNext($started, $expiry);
        $second  = $store->claimNext($expiry, $later);
        self::assertNotNull($first);
        self::assertNotNull($second);
        $firstToken  = $first->claimToken();
        $secondToken = $second->claimToken();
        self::assertNotSame($firstToken, $secondToken);
        $store->markPublished($first, $expiry);
        $store->recordPublicationFailure($first, 9, $later, 'stale');
        $connection = $this->connection;
        $row        = $connection->fetchAssociative('SELECT published_at, claim_token, attempts, last_failure_type FROM integration_event_outbox');
        self::assertIsArray($row);
        self::assertNull($row['published_at']);
        self::assertSame($secondToken, $row['claim_token']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['last_failure_type']);
        $store->markPublished($second, $expiry);
        self::assertSame('2026-10-14 10:01:00.000000', $connection->fetchOne('SELECT published_at FROM integration_event_outbox'));
    }
}
