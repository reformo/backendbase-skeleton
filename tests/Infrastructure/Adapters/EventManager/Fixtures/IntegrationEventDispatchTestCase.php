<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager\Fixtures;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Persistence\IntegrationEventTransaction;
use Backendbase\Shared\Services\EventManager\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Infrastructure\Composition\ProductionContainerFixture;

abstract class IntegrationEventDispatchTestCase extends TestCase
{
    protected Connection $connection;
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE aggregate_write (id TEXT PRIMARY KEY)');
        $connection->executeStatement(
            'CREATE TABLE integration_event_outbox ('
            . 'id TEXT PRIMARY KEY, event_name TEXT, event_version TEXT, payload TEXT, '
            . 'occurred_at TEXT, created_at TEXT, available_at TEXT, published_at TEXT, '
            . 'attempts INTEGER, last_error TEXT)',
        );
        $connection->executeStatement(
            'CREATE TABLE subscriber_write (event_name TEXT, business_count INTEGER, outbox_count INTEGER)',
        );
        $this->connection = $connection;
        $this->container  = ProductionContainerFixture::build([
            Connection::class => $connection,
            LoggerInterface::class => new NullLogger(),
        ]);
        $eventManager     = $this->eventManager();
        $eventManager->addEventSubscriber(
            PersistIntegrationEventSubscriber::getSubscribedEvents(),
            PersistIntegrationEventSubscriber::class,
        );
    }

    protected function eventManager(): EventManager
    {
        $container    = $this->container;
        $eventManager = $container->get(EventManager::class);
        self::assertInstanceOf(EventManager::class, $eventManager);

        return $eventManager;
    }

    protected function executeEvent(IntegrationEvent $event): void
    {
        $container   = $this->container;
        $transaction = $container->get(IntegrationEventTransaction::class);
        self::assertInstanceOf(IntegrationEventTransaction::class, $transaction);
        $connection = $this->connection;
        $transaction->execute(static function () use ($connection, $event): IntegrationEvent {
            $connection->insert('aggregate_write', ['id' => 'example-id']);

            return $event;
        });
    }

    protected function assertNoWrites(): void
    {
        self::assertSame(0, $this->rowCount('aggregate_write'));
        self::assertSame(0, $this->rowCount('subscriber_write'));
        self::assertSame(0, $this->rowCount('integration_event_outbox'));
    }

    protected function rowCount(string $table): int
    {
        $connection = $this->connection;

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
