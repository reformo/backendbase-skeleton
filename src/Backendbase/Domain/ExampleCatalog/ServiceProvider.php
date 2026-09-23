<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryReadRepository as DoctrineEntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryWriteRepository as DoctrineEntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\GreetingRequestedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\IntegrationEventSubscribers\EntryAddedSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Override;

class ServiceProvider implements \Backendbase\Shared\ServiceProvider
{
    /** @return iterable<class-string, class-string> */
    #[Override]
    public static function getDefinitions(): iterable
    {
        return [
            EntryReadRepository::class => DoctrineEntryReadRepository::class,
            EntryWriteRepository::class => DoctrineEntryWriteRepository::class,
        ];
    }

    /**
     * @return iterable<int, array{
     *     events: array<int, string>,
     *     subscriberFQCN: class-string,
     *     messageFQCN?: class-string,
     *     eventVersion?: string
     * }>
     */
    #[Override]
    public static function getIntegrationEventSubscribers(): iterable
    {
        return [
            ['events' => EntryAddedSubscriber::getSubscribedEvents(), 'subscriberFQCN' => EntryAddedSubscriber::class],
            [
                'events' => EntryAddedExternalSubscriber::getSubscribedEvents(),
                'subscriberFQCN' => EntryAddedExternalSubscriber::class,
                'messageFQCN' => EntryAddedMessage::class,
                'eventVersion' => EntryAdded::EVENT_VERSION,
            ],
            [
                'events' => GreetingRequestedExternalSubscriber::getSubscribedEvents(),
                'subscriberFQCN' => GreetingRequestedExternalSubscriber::class,
                'messageFQCN' => GreetingRequestedPayload::class,
                'eventVersion' => GreetingRequested::EVENT_VERSION,
            ],
        ];
    }
}
