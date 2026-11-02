<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Messaging;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineOutboxMessageStore;
use Backendbase\Infrastructure\Messaging\OutboxRelayService;
use Backendbase\Shared\Integrations\Operation\OutboxPublicationResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\Persistence\EntityManagerFactory;
use Tests\Support\Time\FrozenClock;
use Throwable;

final class OutboxInboxInterruptionTest extends TestCase
{
    public function testDuplicatePublicationAfterMarkFailureDoesNotRepeatTheDatabaseMutation(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_outbox (id TEXT PRIMARY KEY, event_name TEXT, event_version TEXT, payload TEXT, occurred_at TEXT, destination TEXT, created_at TEXT, available_at TEXT, published_at TEXT, claim_token TEXT, claim_expires_at TEXT, attempts INTEGER, last_failure_type TEXT)');
        $connection->executeStatement('CREATE TABLE integration_event_inbox (consumer_name TEXT, message_id TEXT, event_name TEXT, received_at TEXT, processed_at TEXT, PRIMARY KEY (consumer_name, message_id))');
        $connection->executeStatement('CREATE TABLE subscriber_write (id TEXT PRIMARY KEY)');
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
        $connection->executeStatement("CREATE TRIGGER reject_publication BEFORE UPDATE OF published_at ON integration_event_outbox WHEN NEW.published_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'synthetic mark failure'); END");
        $clock       = new FrozenClock(new DateTimeImmutable('2026-10-14 10:00:00 UTC'));
        $deliveries  = [];
        $publisher   = $this->createMock(OutboxPublisher::class);
        $expectation = $publisher->expects(self::exactly(2));
        $method      = $expectation->method('publish');
        $method->willReturnCallback(static function (ClaimedOutboxMessage $message) use (&$deliveries): OutboxPublicationResult {
            $deliveries[] = $message;

            return OutboxPublicationResult::succeeded();
        });
        $store = new DoctrineOutboxMessageStore($connection);
        $relay = new OutboxRelayService($store, $publisher, $clock);
        try {
            $relay->relay(1);
            self::fail('Publication marking must fail after the broker accepts the message.');
        } catch (Throwable $exception) {
            self::assertStringContainsString('synthetic mark failure', $exception->getMessage());
        }

        self::assertCount(1, $deliveries);
        $inbox   = new DoctrineInboxMessageTransaction(EntityManagerFactory::create($connection), $clock);
        $handler = static function () use ($connection): void {
            $connection->insert('subscriber_write', ['id' => 'write-id']);
        };
        $firstId = $deliveries[0]->id();
        $inbox->processOnce('events', $firstId, 'Example_Changed', $handler);
        $connection->executeStatement('DROP TRIGGER reject_publication');
        $clock->advance(new DateInterval('PT60S'));
        $result = $relay->relay(1);
        self::assertSame(1, $result->published());
        self::assertCount(2, $deliveries);
        $secondId = $deliveries[1]->id();
        self::assertSame($firstId, $secondId);
        $inbox->processOnce('events', $secondId, 'Example_Changed', $handler);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM subscriber_write'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
    }
}
