<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext\NewExampleAddedExternalSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Application\IntegrationEventSubscribers\NewExampleAddedSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedCommand;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedMessage;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\ExampleRemoved;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\NewExampleAddedPayload;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareEventManager;
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
            [NewExampleAdded::EVENT_TYPE, 'Example_*'],
            NewExampleAddedSubscriber::class,
        );
        $eventManager->dispatchEvent(new ExampleRemoved('example-id'));
        $eventManager->dispatchEvent(new NewExampleAdded(new NewExampleAddedPayload(
            'example-id',
            'system',
            null,
            'group',
            true,
            'key',
            'value',
            [],
        )));

        self::assertSame(2, $this->matchingLogCount($logHandler, 'NewExampleAddedSubscriber'));
        self::assertTrue($eventManager->hasSubscriber('Example_ExampleChanged'));
    }

    #[Test]
    public function itDispatchesAndReturnsMatchingExternalWildcardSubscribers(): void
    {
        [$eventManager, $logHandler] = $this->eventManager();
        $eventManager->addEventSubscriber(
            'Partner_*_Event',
            NewExampleAddedExternalSubscriber::class,
        );

        $subscribers = $eventManager->getSubscriber('Partner_ExampleAdded_Event');
        $eventManager->dispatchExternalEvent(
            'Partner_ExampleAdded_Event',
            new NewExampleAddedMessage(
                'example-id',
                new NewExampleAddedCommand(
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

        self::assertContains(NewExampleAddedExternalSubscriber::class, $subscribers);
        self::assertSame(1, $this->matchingLogCount($logHandler, 'NewExampleAddedExternalSubscriber'));
    }

    #[Test]
    public function itRemovesAWildcardSubscriber(): void
    {
        [$eventManager, $logHandler] = $this->eventManager();
        $eventManager->addEventSubscriber('Example_*', NewExampleAddedSubscriber::class);
        $eventManager->removeEventSubscriber('Example_*', NewExampleAddedSubscriber::class);

        $eventManager->dispatchEvent(new ExampleRemoved('example-id'));

        self::assertSame(0, $this->matchingLogCount($logHandler, 'NewExampleAddedSubscriber'));
    }

    #[Test]
    public function itIgnoresAnExternalEventWithoutSubscribers(): void
    {
        [$eventManager] = $this->eventManager();

        $eventManager->dispatchExternalEvent(
            'Missing_Event',
            new NewExampleAddedMessage(
                'example-id',
                new NewExampleAddedCommand(
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
            ExampleRemoved::EVENT_TYPE,
            NewExampleAddedExternalSubscriber::class,
        );

        $this->expectException(UnexpectedValueException::class);

        $eventManager->dispatchEvent(new ExampleRemoved('example-id'));
    }

    #[Test]
    public function itRejectsAnInternalSubscriberForAnExternalEvent(): void
    {
        [$eventManager] = $this->eventManager();
        $eventManager->addEventSubscriber('Partner_Event', NewExampleAddedSubscriber::class);

        $this->expectException(UnexpectedValueException::class);

        $eventManager->dispatchExternalEvent(
            'Partner_Event',
            new NewExampleAddedMessage(
                'example-id',
                new NewExampleAddedCommand(
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
        $eventManager->addEventSubscriber('Exact_Event', NewExampleAddedSubscriber::class);
        $eventManager->addEventSubscriber('Wildcard_*', NewExampleAddedExternalSubscriber::class);

        $subscribers = $eventManager->getAllSubscribers();

        self::assertArrayHasKey('Exact_Event', $subscribers);
        self::assertArrayHasKey('Wildcard_*', $subscribers);
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
