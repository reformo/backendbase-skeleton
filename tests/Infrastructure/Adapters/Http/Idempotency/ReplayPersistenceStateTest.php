<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Laminas\Diactoros\Response\EmptyResponse;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ReplayFixture;
use Tests\Support\Http\ReplayMiddlewareFixture;
use Tests\Support\Time\FrozenClock;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ReplayPersistenceStateTest extends TestCase
{
    public function testCommittedBusinessChangeRemainsBlockedAfterResponseStorageFails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'replay-commit-');
        self::assertIsString($path);
        $claims   = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $business = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $observer = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        try {
            ReplayFixture::schema($claims);
            $claims->executeStatement('CREATE TABLE committed_effect (id INTEGER PRIMARY KEY, description TEXT NOT NULL)');
            $claims->executeStatement("CREATE TRIGGER reject_replay_response BEFORE UPDATE OF response ON http_request_replay BEGIN SELECT RAISE(ABORT, 'synthetic storage failure'); END");
            $clock = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
            $store = new DoctrineRequestReplayStore($claims, $clock);
            $calls = 0;
            $app   = ReplayMiddlewareFixture::app(static function () use ($business, &$calls): EmptyResponse {
                $calls++;
                $business->beginTransaction();
                $business->insert('committed_effect', ['description' => 'committed once']);
                $business->commit();

                return new EmptyResponse();
            }, $store);

            $first = $app->handle(ReplayMiddlewareFixture::request());
            self::assertSame(503, $first->getStatusCode());
            self::assertStringNotContainsString('synthetic storage failure', (string) $first->getBody());
            self::assertSame(1, (int) $observer->fetchOne('SELECT COUNT(*) FROM committed_effect'));
            $claim = $observer->fetchAssociative('SELECT response, expires_at FROM http_request_replay');
            self::assertIsArray($claim);
            self::assertNull($claim['response']);
            self::assertNull($claim['expires_at']);

            $retry = $app->handle(ReplayMiddlewareFixture::request());
            self::assertSame(409, $retry->getStatusCode());
            $changed = $app->handle(ReplayMiddlewareFixture::request(body: '{"changed":true}'));
            self::assertSame(422, $changed->getStatusCode());
            $clock->advance(new DateInterval('P30D'));
            self::assertSame(0, $store->deleteExpired());
            $lateRetry = $app->handle(ReplayMiddlewareFixture::request());
            self::assertSame(409, $lateRetry->getStatusCode());
            self::assertSame(1, $calls);
            self::assertSame(1, (int) $observer->fetchOne('SELECT COUNT(*) FROM committed_effect'));
        } finally {
            $observer->close();
            $business->close();
            $claims->close();
            unlink($path);
        }
    }

    public function testCorruptCiphertextCannotReplayOrRepeatTheOperation(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        ReplayFixture::schema($connection);
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
        $store = new DoctrineRequestReplayStore($connection, $clock);
        $calls = 0;
        $app   = ReplayMiddlewareFixture::app(static function () use (&$calls): EmptyResponse {
            $calls++;

            return new EmptyResponse();
        }, $store);
        $first = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(204, $first->getStatusCode());
        $connection->executeStatement("UPDATE http_request_replay SET response = 'corrupt ciphertext'");

        $retry = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(503, $retry->getStatusCode());
        self::assertSame(1, $calls);
        self::assertSame('corrupt ciphertext', $connection->fetchOne('SELECT response FROM http_request_replay'));
    }
}
