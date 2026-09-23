<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleReadRepository as DoctrineExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleWriteRepository as DoctrineExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext\GreetingRequestedExternalSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext\NewExampleAddedExternalSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Application\IntegrationEventSubscribers\NewExampleAddedSubscriber;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedMessage;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\GreetingRequested;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\V1\GreetingRequestedPayload;
use Override;

class ServiceProvider implements \Backendbase\Shared\ServiceProvider
{
    /** @return iterable<class-string, class-string> */
    #[Override]
    public static function getDefinitions(): iterable
    {
        return [
            ExampleReadRepository::class => DoctrineExampleReadRepository::class,
            ExampleWriteRepository::class => DoctrineExampleWriteRepository::class,
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
            ['events' => NewExampleAddedSubscriber::getSubscribedEvents(), 'subscriberFQCN' => NewExampleAddedSubscriber::class],
            [
                'events' => NewExampleAddedExternalSubscriber::getSubscribedEvents(),
                'subscriberFQCN' => NewExampleAddedExternalSubscriber::class,
                'messageFQCN' => NewExampleAddedMessage::class,
                'eventVersion' => NewExampleAdded::EVENT_VERSION,
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
