<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\IntegrationEventSubscribers\EntryAddedSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedCommand;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareEventManager;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareSubscriberRegistry;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use DI\Container;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

use function array_filter;
use function count;

final class ContainerAwareEventManagerTest extends TestCase
{
    #[Test]
    public function itDispatchesMatchingWildcardAndExactSubscribersOnce(): void
    {
        [$eventManager, $logHandler] = $this->eventManager();
        $eventManager->addEventSubscriber(
            [EntryAdded::EVENT_TYPE, 'Example_*'],
            EntryAddedSubscriber::class,
        );
        $eventManager->dispatchEvent(new EntryRemoved('example-id'));
        $eventManager->dispatchEvent(new EntryAdded(new EntryAddedPayload(
            'example-id',
            'system',
            null,
            'group',
            true,
            'key',
            'value',
            [],
        )));

        self::assertSame(2, $this->matchingLogCount($logHandler, 'EntryAddedSubscriber'));
        self::assertTrue($eventManager->hasSubscriber('Example_ExampleChanged'));
    }

    #[Test]
    public function itDispatchesAndReturnsMatchingExternalWildcardSubscribers(): void
    {
        [$eventManager, $logHandler] = $this->eventManager();
        $eventManager->addEventSubscriber(
            'Partner_*_Event',
            EntryAddedExternalSubscriber::class,
        );

        $subscribers = $eventManager->getSubscriber('Partner_ExampleAdded_Event');
        $eventManager->dispatchExternalEvent(
            'Partner_ExampleAdded_Event',
            new EntryAddedMessage(
                'example-id',
                new EntryAddedCommand(
                    'example-id',
                    'system',
                    null,
                    'group',
                    true,
                    'key',
                    'value',
                    [],
                ),
            ),
        );

        self::assertContains(EntryAddedExternalSubscriber::class, $subscribers);
        self::assertSame(1, $this->matchingLogCount($logHandler, 'EntryAddedExternalSubscriber'));
    }

    #[Test]
    public function itRemovesAWildcardSubscriber(): void
    {
        [$eventManager, $logHandler] = $this->eventManager();
        $eventManager->addEventSubscriber('Example_*', EntryAddedSubscriber::class);
        $eventManager->removeEventSubscriber('Example_*', EntryAddedSubscriber::class);

        $eventManager->dispatchEvent(new EntryRemoved('example-id'));

        self::assertSame(0, $this->matchingLogCount($logHandler, 'EntryAddedSubscriber'));
    }

    #[Test]
    public function itIgnoresAnExternalEventWithoutSubscribers(): void
    {
        [$eventManager] = $this->eventManager();

        $eventManager->dispatchExternalEvent(
            'Missing_Event',
            new EntryAddedMessage(
                'example-id',
                new EntryAddedCommand(
                    'example-id',
                    'system',
                    null,
                    'group',
                    true,
                    'key',
                    'value',
                    [],
                ),
            ),
        );

        self::assertFalse($eventManager->hasSubscriber('Missing_Event'));
    }

    #[Test]
    public function itRejectsAnExternalSubscriberForAnInternalEvent(): void
    {
        [$eventManager] = $this->eventManager();
        $eventManager->addEventSubscriber(
            EntryRemoved::EVENT_TYPE,
            EntryAddedExternalSubscriber::class,
        );

        $this->expectException(UnexpectedValueException::class);

        $eventManager->dispatchEvent(new EntryRemoved('example-id'));
    }

    #[Test]
    public function itRejectsAnInternalSubscriberForAnExternalEvent(): void
    {
        [$eventManager] = $this->eventManager();
        $eventManager->addEventSubscriber('Partner_Event', EntryAddedSubscriber::class);

        $this->expectException(UnexpectedValueException::class);

        $eventManager->dispatchExternalEvent(
            'Partner_Event',
            new EntryAddedMessage(
                'example-id',
                new EntryAddedCommand(
                    'example-id',
                    'system',
                    null,
                    'group',
                    true,
                    'key',
                    'value',
                    [],
                ),
            ),
        );
    }

    #[Test]
    public function itReturnsAllExactAndWildcardSubscribers(): void
    {
        [$eventManager] = $this->eventManager();
        $eventManager->addEventSubscriber('Exact_Event', EntryAddedSubscriber::class);
        $eventManager->addEventSubscriber('Wildcard_*', EntryAddedExternalSubscriber::class);

        $subscribers = $eventManager->getAllSubscribers();

        self::assertArrayHasKey('Exact_Event', $subscribers);
        self::assertArrayHasKey('Wildcard_*', $subscribers);
    }

    #[Test]
    public function itResolvesABuiltInConstructorArgumentByName(): void
    {
        $container = new Container();
        $container->set('subscriberName', 'resolved-subscriber');
        $registry = new ContainerAwareSubscriberRegistry($container);

        $subscriber = $registry->resolve(NamedArgumentSubscriber::class);

        self::assertInstanceOf(NamedArgumentSubscriber::class, $subscriber);
        self::assertSame('resolved-subscriber', $subscriber->subscriberName());
    }

    /** @return array{ContainerAwareEventManager, TestHandler} */
    private function eventManager(): array
    {
        $logger     = new Logger('event-manager-test');
        $logHandler = new TestHandler();
        $logger->pushHandler($logHandler);

        $container = new Container();
        $container->set(LoggerInterface::class, $logger);

        return [
            new ContainerAwareEventManager(
                $container,
                $logger,
                $this->createStub(IntegrationEventOutbox::class),
            ),
            $logHandler,
        ];
    }

    private function matchingLogCount(TestHandler $logHandler, string $message): int
    {
        return count(array_filter(
            $logHandler->getRecords(),
            static fn ($record): bool => $record->message === $message,
        ));
    }
}
