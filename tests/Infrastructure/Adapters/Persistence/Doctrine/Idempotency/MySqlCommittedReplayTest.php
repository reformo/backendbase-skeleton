<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Migrations\Version20261018000000;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Laminas\Diactoros\Response\EmptyResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Http\ReplayMiddlewareFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;

final class MySqlCommittedReplayTest extends TestCase
{
    public function testCommittedWriteCannotRepeatWhenResponseStorageFails(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture  = IsolatedMySql::create(false);
        $business = null;
        $observer = null;
        try {
            $business = $fixture->anotherConnection();
            $observer = $fixture->anotherConnection();
            $claims   = $fixture->connection();
            $this->createTables($claims);
            $this->assertBlockedAfterCommit($claims, $business, $observer);
        } finally {
            $observer?->close();
            $business?->close();
            $fixture->destroy();
        }
    }

    private function createTables(Connection $connection): void
    {
        $migration = new Version20261018000000($connection, new NullLogger());
        $migration->up(new Schema());
        $queries   = $migration->getSql();
        $statement = $queries[0]->getStatement();
        $connection->executeStatement($statement);
        $connection->executeStatement('CREATE TABLE committed_effect (id INT PRIMARY KEY, description VARCHAR(80) NOT NULL) ENGINE=InnoDB');
        $connection->executeStatement("CREATE TRIGGER reject_replay_response BEFORE UPDATE ON http_request_replay FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic storage failure'");
    }

    private function assertBlockedAfterCommit(Connection $claims, Connection $business, Connection $observer): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
        $store = new DoctrineRequestReplayStore($claims, $clock);
        $calls = 0;
        $app   = ReplayMiddlewareFixture::app(static function () use ($business, &$calls): EmptyResponse {
            $calls++;
            $business->beginTransaction();
            $business->insert('committed_effect', ['id' => 1, 'description' => 'committed once']);
            $business->commit();

            return new EmptyResponse();
        }, $store);

        $first = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(503, $first->getStatusCode());
        self::assertSame(1, (int) $observer->fetchOne('SELECT COUNT(*) FROM committed_effect'));
        $claim = $observer->fetchAssociative('SELECT response, expires_at FROM http_request_replay');
        self::assertIsArray($claim);
        self::assertNull($claim['response']);
        self::assertNull($claim['expires_at']);
        $retry = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(409, $retry->getStatusCode());
        $clock->advance(new DateInterval('P30D'));
        self::assertSame(0, $store->deleteExpired());
        $lateRetry = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(409, $lateRetry->getStatusCode());
        self::assertSame(1, $calls);
        self::assertSame(1, (int) $observer->fetchOne('SELECT COUNT(*) FROM committed_effect'));
    }
}
