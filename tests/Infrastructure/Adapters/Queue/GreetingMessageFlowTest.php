<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\GreetingRequestedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Backendbase\Domain\ExampleCatalog\ServiceProvider;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareEventManager;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

use function iterator_to_array;

use const PHP_EOL;

final class GreetingMessageFlowTest extends TestCase
{
    #[Test]
    public function itPrintsTheGreetingOnceForDuplicateDelivery(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE integration_event_inbox ('
            . 'consumer_name TEXT NOT NULL, message_id TEXT NOT NULL, event_name TEXT NOT NULL, '
            . 'received_at TEXT NOT NULL, processed_at TEXT NULL, PRIMARY KEY (consumer_name, message_id))',
        );
        $subscriber = iterator_to_array(ServiceProvider::getIntegrationEventSubscribers())[2];
        self::assertSame(GreetingRequestedExternalSubscriber::class, $subscriber['subscriberFQCN']);
        if (! isset($subscriber['messageFQCN'], $subscriber['eventVersion'])) {
            self::fail('The greeting subscriber contract is incomplete.');
        }

        $outbox = $this->createMock(IntegrationEventOutbox::class);
        $outbox->expects(self::never())->method('assertTransactionActive');
        $outbox->expects(self::never())->method('append');
        $eventManager = new ContainerAwareEventManager((new ContainerBuilder())->build(), new NullLogger(), $outbox);
        $eventManager->addEventSubscriber($subscriber['events'], $subscriber['subscriberFQCN']);
        $registry      = new InMemoryExternalIntegrationEventRegistry([
            [
                'eventName' => GreetingRequestedExternalSubscriber::EVENT_TYPE,
                'eventVersion' => $subscriber['eventVersion'],
                'messageFQCN' => $subscriber['messageFQCN'],
            ],
        ]);
        $failurePolicy = $this->createMock(QueueMessageFailurePolicy::class);
        $failurePolicy->expects(self::exactly(2))->method('succeeded');
        $failurePolicy->expects(self::never())->method('transientFailure');
        $failurePolicy->expects(self::never())->method('permanentFailure');
        $processor = new ExternalIntegrationEventMessageProcessor(
            new ExternalIntegrationEventDispatcher($eventManager, $registry),
            new DoctrineInboxMessageTransaction($connection),
            $failurePolicy,
            new NullLogger(),
        );
        $payload   = new GreetingRequestedPayload('Ada Lovelace');
        self::assertSame(['fullname' => 'Ada Lovelace'], $payload->jsonSerialize());
        $event   = new GreetingRequested($payload);
        $message = new Message(
            $event->eventName(),
            $event->getEventArguments(),
            'greeting-id',
            $event->eventVersion(),
            'backendbase-queue',
        );

        $this->expectOutputString('hello Ada Lovelace' . PHP_EOL);
        self::assertSame(
            [QueueMessageHandlingOutcome::ACKNOWLEDGE, QueueMessageHandlingOutcome::ACKNOWLEDGE],
            [$processor->process($message), $processor->process($message)],
        );
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
    }

    #[Test]
    public function itRejectsAnUnrelatedEventCarrier(): void
    {
        $subscriber = new GreetingRequestedExternalSubscriber();

        $this->expectException(UnexpectedValueException::class);

        $subscriber->handle($this->createStub(EventMessage::class));
    }
}
