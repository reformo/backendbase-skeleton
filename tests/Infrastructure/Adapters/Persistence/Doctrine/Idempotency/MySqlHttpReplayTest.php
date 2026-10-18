<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Migrations\Version20261018000000;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Laminas\Diactoros\Response\JsonResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Http\ReplayMiddlewareFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;
use function hash;

final class MySqlHttpReplayTest extends TestCase
{
    public function testHttpReplayConflictsExpiryAndUnresolvedClaimsOnMySql(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        $second  = $fixture->anotherConnection();
        try {
            $connection = $fixture->connection();
            $migration  = new Version20261018000000($connection, new NullLogger());
            $migration->up(new Schema());
            $queries   = $migration->getSql();
            $statement = $queries[0]->getStatement();
            $connection->executeStatement($statement);
            $this->assertHttpBehavior($connection, $second);
        } finally {
            $second->close();
            $fixture->destroy();
        }
    }

    private function assertHttpBehavior(Connection $connection, Connection $second): void
    {
        $clock      = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
        $owner      = new DoctrineRequestReplayStore($connection, $clock);
        $retryStore = new DoctrineRequestReplayStore($second, $clock);
        $competitor = ReplayMiddlewareFixture::app(static function (): never {
            self::fail('The competing connection must not execute the operation.');
        }, $retryStore);
        $calls      = 0;
        $app        = ReplayMiddlewareFixture::app(static function () use ($competitor, &$calls): JsonResponse {
            $calls++;
            $concurrent = $competitor->handle(ReplayMiddlewareFixture::request());
            self::assertSame(409, $concurrent->getStatusCode());

            return new JsonResponse(['session' => ['accessToken' => 'native-response-secret']], 201, ['Cache-Control' => 'no-store']);
        }, $owner);
        $first      = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(201, $first->getStatusCode());
        $original = (string) $first->getBody();
        $traced   = ReplayMiddlewareFixture::request()->withHeader('X-Request-Id', 'new-attempt');
        $replay   = $competitor->handle($traced);
        self::assertSame(201, $replay->getStatusCode());
        self::assertSame($original, (string) $replay->getBody());
        self::assertSame('no-store', $replay->getHeaderLine('Cache-Control'));
        self::assertSame(1, $calls);
        $stored = $connection->fetchOne('SELECT response FROM http_request_replay');
        self::assertIsString($stored);
        self::assertStringNotContainsString('native-response-secret', $stored);
        $changed = $app->handle(ReplayMiddlewareFixture::request(body: '{"changed":true}'));
        self::assertSame(422, $changed->getStatusCode());
        self::assertSame(1, $calls);
        $expires = $connection->fetchOne('SELECT expires_at FROM http_request_replay');
        $clock->advance(new DateInterval('PT23H'));
        $stillStored = $competitor->handle(ReplayMiddlewareFixture::request());
        self::assertSame(201, $stillStored->getStatusCode());
        self::assertSame($expires, $connection->fetchOne('SELECT expires_at FROM http_request_replay'));
        self::assertSame(0, $owner->deleteExpired());
        $clock->advance(new DateInterval('PT1H'));
        $replacement = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(201, $replacement->getStatusCode());
        self::assertSame(2, $calls);
        $pending = new RequestIdentity(hash('sha256', 'unresolved-key'), hash('sha256', 'body'));
        $owner->claim($pending);
        $clock->advance(new DateInterval('P30D'));
        self::assertSame(1, $owner->deleteExpired());
        self::assertSame(0, $owner->deleteExpired());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM http_request_replay'));
        $this->expectException(RequestOutstanding::class);
        $retryStore->claim($pending);
    }
}
