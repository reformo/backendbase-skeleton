<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Queue;

use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\ServiceProvider;
use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventDispatcher;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Services\EventManager\EventManager;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProducerConsumerContractTest extends TestCase
{
    #[Test]
    public function itMapsTheProducerPayloadToTheRegisteredConsumerMessage(): void
    {
        $event        = new EntryAdded(new EntryAddedPayload(
            'example-id',
            'system',
            42,
            'settings',
            true,
            'page-size',
            '25',
            ['unit' => 'items'],
        ), new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('getSubscriber')->willReturn([
            'subscriber' => EntryAddedExternalSubscriber::class,
        ]);
        $eventManager->expects(self::once())
            ->method('dispatchExternalEvent')
            ->with(
                EntryAddedExternalSubscriber::EVENT_TYPE,
                self::callback(static function (EntryAddedMessage $message) use ($event): bool {
                    self::assertSame($event->getEventArguments(), $message->toArray());
                    self::assertSame('system', $message->type());
                    self::assertSame(42, $message->typeTargetId());

                    return true;
                }),
            );
        $dispatcher = new ExternalIntegrationEventDispatcher(
            $eventManager,
            new InMemoryExternalIntegrationEventRegistry(self::registeredMessageContracts()),
        );

        $dispatcher->dispatch(
            EntryAddedExternalSubscriber::EVENT_TYPE,
            $event->eventVersion(),
            $event->getEventArguments(),
        );
    }

    /**
     * @return list<array{
     *     'event-name': string,
     *     'event-version': string,
     *     'message-fqcn': class-string
     * }>
     */
    private static function registeredMessageContracts(): array
    {
        $contracts = [];
        foreach (ServiceProvider::getIntegrationEventSubscribers() as $subscriber) {
            if (! isset($subscriber['message-fqcn'], $subscriber['event-version'])) {
                continue;
            }

            foreach ($subscriber['events'] as $eventName) {
                $contracts[] = [
                    'event-name' => $eventName,
                    'event-version' => $subscriber['event-version'],
                    'message-fqcn' => $subscriber['message-fqcn'],
                ];
            }
        }

        return $contracts;
    }
}
