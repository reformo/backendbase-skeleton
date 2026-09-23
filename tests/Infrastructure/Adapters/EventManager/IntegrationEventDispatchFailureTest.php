<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Infrastructure\Adapters\EventManager\Fixtures\FailingIntegrationEventSubscriber;
use Tests\Infrastructure\Adapters\EventManager\Fixtures\IntegrationEventDispatchTestCase;

final class IntegrationEventDispatchFailureTest extends IntegrationEventDispatchTestCase
{
    #[Test]
    public function itRollsBackBusinessAndEarlierSubscriberWritesWhenASubscriberThrows(): void
    {
        $eventManager = $this->eventManager();
        $eventManager->addEventSubscriber(
            FailingIntegrationEventSubscriber::getSubscribedEvents(),
            FailingIntegrationEventSubscriber::class,
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The local integration subscriber failed.');

        try {
            $this->executeEvent(new EntryRemoved('example-id'));
        } finally {
            $this->assertNoWrites();
        }
    }

    #[Test]
    public function itRollsBackBusinessAndLocalSubscriberWritesWhenTheOutboxInsertFails(): void
    {
        $connection = $this->connection;
        $connection->executeStatement(
            'CREATE TRIGGER fail_outbox BEFORE INSERT ON integration_event_outbox '
            . "BEGIN SELECT RAISE(ABORT, 'Outbox insert failed.'); END",
        );
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Outbox insert failed.');

        try {
            $this->executeEvent(new EntryRemoved('example-id'));
        } finally {
            $this->assertNoWrites();
        }
    }
}
