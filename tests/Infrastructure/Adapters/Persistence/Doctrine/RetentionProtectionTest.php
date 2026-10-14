<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineIntegrationMessageLogCleaner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Persistence\MessageRecoveryFixture;

final class RetentionProtectionTest extends TestCase
{
    /** @param array{int, int, int} $counts */
    #[DataProvider('recoveryStates')]
    public function testRequiredRecoveryRecordsSurviveCleanup(string|null $published, string|null $processed, string|null $terminal, array $counts): void
    {
        $fixture = MessageRecoveryFixture::create();
        $fixture->outbox(['published_at' => $published]);
        $fixture->inbox(['processed_at' => $processed]);
        $connection = $fixture->connection();
        $connection->insert('integration_event_delivery_failure', ['consumer_name' => 'events', 'message_id' => 'original-message', 'dead_lettered_at' => $terminal]);
        $result = new DoctrineIntegrationMessageLogCleaner($connection, $fixture->clock())->clean(30, 100);
        self::assertSame($counts, [$result->outboxMessages(), $result->inboxMessages(), $result->deliveryFailures()]);
    }

    /** @return iterable<string, array{string|null, string|null, string|null, array{int, int, int}}> */
    public static function recoveryStates(): iterable
    {
        $old = '2026-09-13 10:00:00.000000';

        yield 'unknown effect preserves outbox and failure' => [$old, null, $old, [0, 0, 0]];
        yield 'pending outbox preserves inbox and failure' => [null, $old, $old, [0, 0, 0]];
        yield 'retrying delivery preserves outbox and inbox' => [$old, $old, null, [0, 0, 0]];
        yield 'all completed' => [$old, $old, $old, [1, 1, 1]];
    }

    #[DataProvider('invalidLimits')]
    public function testCleanupLimitsAreBounded(int $limit): void
    {
        $fixture = MessageRecoveryFixture::create();
        $this->expectException(InvalidArgumentException::class);
        new DoctrineIntegrationMessageLogCleaner($fixture->connection(), $fixture->clock())->clean(30, $limit);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'oversized' => [10001];
    }
}
