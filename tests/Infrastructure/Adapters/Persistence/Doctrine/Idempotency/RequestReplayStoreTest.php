<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Idempotency;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestMismatch;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ReplayFixture;
use Tests\Support\Time\FrozenClock;

use function hash;

final class RequestReplayStoreTest extends TestCase
{
    public function testItClaimsOnceAndReplaysTheCompletedResponse(): void
    {
        [$store, $connection] = $this->fixture();
        $identity             = $this->identity();
        self::assertNull($store->claim($identity));
        self::assertFalse($connection->isTransactionActive());
        $store->complete($identity, 'encrypted-response');
        self::assertSame('encrypted-response', $store->claim($identity));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM http_request_replay'));
    }

    public function testOutstandingClaimsNeverExpire(): void
    {
        [$store, , $clock] = $this->fixture();
        $identity          = $this->identity();
        $store->claim($identity);
        $clock->advance(new DateInterval('P30D'));
        self::assertSame(0, $store->deleteExpired());
        $this->expectException(RequestOutstanding::class);
        $store->claim($identity);
    }

    public function testAChangedPayloadCannotReuseTheKey(): void
    {
        [$store]  = $this->fixture();
        $identity = $this->identity();
        $store->claim($identity);
        $this->expectException(RequestMismatch::class);
        $different = $this->identity('different');
        $store->claim($different);
    }

    public function testCompletionExpiresAfterTwentyFourHoursWithoutRenewal(): void
    {
        [$store, , $clock] = $this->fixture();
        $identity          = $this->identity();
        $store->claim($identity);
        $store->complete($identity, 'encrypted');
        $clock->advance(new DateInterval('PT23H'));
        self::assertSame('encrypted', $store->claim($identity));
        self::assertSame(0, $store->deleteExpired());
        $clock->advance(new DateInterval('PT1H'));
        self::assertNull($store->claim($identity));
    }

    public function testCleanupPreservesActiveAndUnresolvedClaims(): void
    {
        [$store, $connection, $clock] = $this->fixture();
        $identity                     = $this->identity();
        $store->claim($identity);
        $store->complete($identity, 'encrypted');
        $clock->advance(new DateInterval('P1D'));
        $store->claim(new RequestIdentity(hash('sha256', 'other'), hash('sha256', 'body')));
        self::assertSame(1, $store->deleteExpired());
        self::assertSame(0, $store->deleteExpired());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM http_request_replay'));
    }

    public function testItRejectsClaimsInsideBusinessTransactions(): void
    {
        [$store, $connection] = $this->fixture();
        $connection->beginTransaction();
        $this->expectException(LogicException::class);
        try {
            $identity = $this->identity();
            $store->claim($identity);
        } finally {
            $connection->rollBack();
        }
    }

    /** @return array{DoctrineRequestReplayStore, Connection, FrozenClock} */
    private function fixture(): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        ReplayFixture::schema($connection);
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));

        return [new DoctrineRequestReplayStore($connection, $clock), $connection, $clock];
    }

    private function identity(string $payload = 'body'): RequestIdentity
    {
        return new RequestIdentity(hash('sha256', 'key'), hash('sha256', $payload));
    }
}
