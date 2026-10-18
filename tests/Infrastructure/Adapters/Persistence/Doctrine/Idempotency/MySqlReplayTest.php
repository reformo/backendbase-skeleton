<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Migrations\Version20261018000000;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;
use function hash;

final class MySqlReplayTest extends TestCase
{
    public function testNativeClaimsAndIndexedExpiryOnAPreparedTarget(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            $migration  = new Version20261018000000($connection, new NullLogger());
            $migration->up(new Schema());
            $queries   = $migration->getSql();
            $statement = $queries[0]->getStatement();
            $connection->executeStatement($statement);
            $second = $fixture->anotherConnection();
            $this->assertDurableClaim($connection, $second);
            $this->assertIndexes($connection);
            $second->close();
        } finally {
            $fixture->destroy();
        }
    }

    private function assertDurableClaim(Connection $first, Connection $second): void
    {
        $clock    = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
        $owner    = new DoctrineRequestReplayStore($first, $clock);
        $retry    = new DoctrineRequestReplayStore($second, $clock);
        $identity = new RequestIdentity(hash('sha256', 'key'), hash('sha256', 'body'));
        self::assertNull($owner->claim($identity));
        try {
            $retry->claim($identity);
            self::fail('Concurrent connection must not take over.');
        } catch (RequestOutstanding $exception) {
            self::assertStringContainsString('in progress', $exception->getMessage());
        }

        $owner->complete($identity, 'encrypted-response');
        self::assertSame('encrypted-response', $retry->claim($identity));
    }

    private function assertIndexes(Connection $connection): void
    {
        for ($number = 0; $number < 1000; $number++) {
            $connection->insert('http_request_replay', [
                'request_hash' => hash('sha256', 'fixture-' . $number),
                'fingerprint' => hash('sha256', 'body'),
                'response' => 'encrypted',
                'created_at' => '2026-10-18 10:00:00',
                'expires_at' => $number < 20 ? '2026-10-18 11:00:00' : '2026-10-19 10:00:00',
            ]);
        }

        $identity = hash('sha256', 'key');
        $lookup   = $connection->fetchAssociative('EXPLAIN SELECT fingerprint, response FROM http_request_replay WHERE request_hash = ?', [$identity]);
        self::assertIsArray($lookup);
        self::assertSame('PRIMARY', $lookup['key']);
        $cleanup = $connection->fetchAssociative('EXPLAIN SELECT request_hash FROM http_request_replay WHERE expires_at <= ? ORDER BY expires_at LIMIT 500', ['2026-10-18 12:00:00']);
        self::assertIsArray($cleanup);
        self::assertSame('http_request_replay_expiry_idx', $cleanup['key']);
    }
}
