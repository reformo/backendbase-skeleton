<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infrastructure\Adapters\EventManager\Fixtures\IntegrationEventDispatchTestCase;
use Tests\Infrastructure\Adapters\EventManager\Fixtures\PersistIntegrationEventSubscriber;

final class TransactionalIntegrationEventDispatchTest extends IntegrationEventDispatchTestCase
{
    #[Test]
    #[DataProvider('deliveryModes')]
    public function itRunsLocalSubscribersBeforeOptionalOutboxInsertion(IntegrationEvent $event, int $outboxRows): void
    {
        $this->executeEvent($event);

        self::assertSame(1, $this->rowCount('aggregate_write'));
        self::assertSame(1, $this->rowCount('subscriber_write'));
        self::assertSame($outboxRows, $this->rowCount('integration_event_outbox'));
        $connection = $this->connection;
        self::assertSame(
            ['business_count' => 1, 'outbox_count' => 0],
            $connection->fetchAssociative('SELECT business_count, outbox_count FROM subscriber_write'),
        );
        self::assertFalse($connection->isTransactionActive());
    }

    #[Test]
    public function itPublishesWithoutLocalSubscribers(): void
    {
        $eventManager = $this->eventManager();
        $eventManager->removeEventSubscriber(
            PersistIntegrationEventSubscriber::getSubscribedEvents(),
            PersistIntegrationEventSubscriber::class,
        );

        $this->executeEvent(new EntryRemoved('example-id'));

        self::assertSame(0, $this->rowCount('subscriber_write'));
        self::assertSame(1, $this->rowCount('integration_event_outbox'));
    }

    #[Test]
    #[DataProvider('events')]
    public function itRejectsDispatchWithoutATransactionBeforeRunningSubscribers(IntegrationEvent $event): void
    {
        $eventManager = $this->eventManager();
        $this->expectException(LogicException::class);

        try {
            $eventManager->dispatchEvent($event);
        } finally {
            $this->assertNoWrites();
        }
    }

    #[Test]
    public function itKeepsAllWritesInsideTheOuterTransaction(): void
    {
        $connection = $this->connection;
        $connection->beginTransaction();
        $this->executeEvent(new EntryRemoved('example-id'));
        self::assertSame(1, $this->rowCount('integration_event_outbox'));
        self::assertTrue($connection->isTransactionActive());

        $connection->rollBack();

        $this->assertNoWrites();
    }

    /** @return iterable<string, array{IntegrationEvent, int}> */
    public static function deliveryModes(): iterable
    {
        yield 'local and queue' => [new EntryRemoved('example-id'), 1];
        yield 'local only' => [
            new class ('example-id') extends EntryRemoved {
                public const bool DELIVER_VIA_QUEUE = false;
            },
            0,
        ];
    }

    /** @return iterable<string, array{IntegrationEvent}> */
    public static function events(): iterable
    {
        foreach (self::deliveryModes() as $name => [$event]) {
            yield $name => [$event];
        }
    }
}
