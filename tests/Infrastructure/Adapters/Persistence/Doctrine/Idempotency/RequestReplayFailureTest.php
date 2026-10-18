<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Http\ReplayFixture;
use Tests\Support\Time\FrozenClock;

use function hash;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class RequestReplayFailureTest extends TestCase
{
    public function testAnotherConnectionSeesTheClaimBeforeBusinessWork(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'replay-test-');
        self::assertIsString($path);
        $first  = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $second = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        try {
            ReplayFixture::schema($first);
            $owner    = new DoctrineRequestReplayStore($first, $this->clock());
            $retry    = new DoctrineRequestReplayStore($second, $this->clock());
            $identity = $this->identity();
            $owner->claim($identity);
            $this->assertOutstanding($retry, $identity);
            $owner->complete($identity, 'encrypted');
            self::assertSame('encrypted', $retry->claim($identity));
        } finally {
            $first->close();
            $second->close();
            unlink($path);
        }
    }

    public function testACompletedClaimCannotBeOverwritten(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        ReplayFixture::schema($connection);
        $store    = new DoctrineRequestReplayStore($connection, $this->clock());
        $identity = $this->identity();
        $store->claim($identity);
        $store->complete($identity, 'original');
        $this->expectException(RuntimeException::class);
        $store->complete($identity, 'replacement');
    }

    public function testCorruptStoredResponseCannotReleaseItsClaim(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        ReplayFixture::schema($connection);
        $store    = new DoctrineRequestReplayStore($connection, $this->clock());
        $identity = $this->identity();
        $store->claim($identity);
        $connection->executeStatement("UPDATE http_request_replay SET response = ''");
        $this->expectException(RuntimeException::class);
        $store->claim($identity);
    }

    public function testAClaimDisappearingDuringLookupFailsClosed(): void
    {
        $connection = $this->createStub(Connection::class);
        $insert     = $connection->method('insert');
        $duplicate  = $this->createStub(UniqueConstraintViolationException::class);
        $insert->willThrowException($duplicate);
        $lookup = $connection->method('fetchAssociative');
        $lookup->willReturn(false);
        $store = new DoctrineRequestReplayStore($connection, $this->clock());
        $this->expectException(RuntimeException::class);
        $identity = $this->identity();
        $store->claim($identity);
    }

    private function assertOutstanding(DoctrineRequestReplayStore $store, RequestIdentity $identity): void
    {
        try {
            $store->claim($identity);
            self::fail('The second connection cannot take over a durable claim.');
        } catch (RequestOutstanding $exception) {
            self::assertStringContainsString('in progress', $exception->getMessage());
        }
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));
    }

    private function identity(): RequestIdentity
    {
        return new RequestIdentity(hash('sha256', 'key'), hash('sha256', 'body'));
    }
}
