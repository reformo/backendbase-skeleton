<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryReadRepository as DoctrineEntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryWriteRepository as DoctrineEntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\AddEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\ChangeEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\QueueGreetingHandler;
use Backendbase\Domain\ExampleCatalog\Application\CommandHandlers\RemoveEntryHandler;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\GreetingRequestedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\IntegrationEventSubscribers\EntryAddedSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntriesByGroupHandler;
use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntryByCriteriaHandler;
use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntryGroupsByTypeHandler;
use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntryIdByCriteriaHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
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

    /** @return array<class-string, class-string> */
    #[Override]
    public static function getHandlers(): array
    {
        return [
            AddEntry::class => AddEntryHandler::class,
            ChangeEntry::class => ChangeEntryHandler::class,
            QueueGreeting::class => QueueGreetingHandler::class,
            RemoveEntry::class => RemoveEntryHandler::class,
            GetEntriesByGroup::class => GetEntriesByGroupHandler::class,
            GetEntryByCriteria::class => GetEntryByCriteriaHandler::class,
            GetEntryGroupsByType::class => GetEntryGroupsByTypeHandler::class,
            GetEntryIdByCriteria::class => GetEntryIdByCriteriaHandler::class,
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
