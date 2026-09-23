<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\EventManager;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Backendbase\Shared\Persistence\Outbox\IntegrationEventOutbox;
use Backendbase\Shared\Services\EventManager\EventManager;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ContainerAwareEventManager implements EventManager
{
    private readonly ContainerAwareSubscriberDispatcher $subscribers;

    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        private readonly IntegrationEventOutbox $outbox,
    ) {
        $this->subscribers = new ContainerAwareSubscriberDispatcher($container, $logger);
    }

    #[Override]
    public function dispatchEvent(IntegrationEvent $event): void
    {
        $outbox = $this->outbox;
        $outbox->assertTransactionActive();
        $subscribers = $this->subscribers;
        $subscribers->dispatchEvent($event);
        if (! $event->isExternal()) {
            return;
        }

        $outbox->append($event);
    }

    #[Override]
    public function dispatchExternalEvent(string $eventName, EventMessage $message): void
    {
        $subscribers = $this->subscribers;
        $subscribers->dispatchExternalEvent($eventName, $message);
    }

    /** @return array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>> */
    #[Override]
    public function getSubscriber(string $event): array
    {
        $subscribers = $this->subscribers;

        return $subscribers->getSubscriber($event);
    }

    /** @return array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    #[Override]
    public function getAllSubscribers(): array
    {
        $subscribers = $this->subscribers;

        return $subscribers->getAllSubscribers();
    }

    #[Override]
    public function hasSubscriber(string $event): bool
    {
        $subscribers = $this->subscribers;

        return $subscribers->hasSubscriber($event);
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    #[Override]
    public function addEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $subscribers = $this->subscribers;
        $subscribers->addEventSubscriber($events, $subscriberFQCN);
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    #[Override]
    public function removeEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $subscribers = $this->subscribers;
        $subscribers->removeEventSubscriber($events, $subscriberFQCN);
    }
}
