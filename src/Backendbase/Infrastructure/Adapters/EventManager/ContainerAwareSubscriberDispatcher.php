<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\EventManager;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

final class ContainerAwareSubscriberDispatcher
{
    private readonly ContainerAwareSubscriberRegistry $subscriberRegistry;

    public function __construct(
        ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
        $this->subscriberRegistry = new ContainerAwareSubscriberRegistry($container);
    }

    public function dispatchEvent(IntegrationEvent $event): void
    {
        $this->logger->debug('ContainerAwareEventManager-1', ['event' => $event->eventName()]);
        $subscribers = $this->getSubscriber($event->eventName());
        if ($subscribers === []) {
            return;
        }

        $this->logger->debug('ContainerAwareEventManager-2', ['event' => $event]);
        foreach ($subscribers as $subscriberFQCN) {
            $this->logger->debug('ContainerAwareEventManager-3', ['subscriber' => $subscriberFQCN]);
            $subscriber = $this->subscriberRegistry->resolve($subscriberFQCN);
            if (! $subscriber instanceof IntegrationEventSubscriber) {
                throw new UnexpectedValueException($subscriberFQCN . ' is not an integration event subscriber.');
            }

            $subscriber->handle($event);
        }
    }

    public function dispatchExternalEvent(string $eventName, EventMessage $message): void
    {
        $this->logger->debug('ContainerAwareEventManager-1', ['message' => $message->toArray()]);
        $subscribers = $this->getSubscriber($eventName);
        if ($subscribers === []) {
            return;
        }

        foreach ($subscribers as $subscriberFQCN) {
            $this->logger->debug('ContainerAwareEventManager-3', ['subscriber' => $subscriberFQCN]);
            $subscriber = $this->subscriberRegistry->resolve($subscriberFQCN);
            if (! $subscriber instanceof ExternalIntegrationEventSubscriber) {
                throw new UnexpectedValueException($subscriberFQCN . ' is not an external integration event subscriber.');
            }

            $subscriber->handle($message);
        }
    }

    /** @return array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>> */
    public function getSubscriber(string $event): array
    {
        $subscribers = $this->subscriberRegistry->forEvent($event);

        $this->logger->debug('ContainerAwareEventManager-5', ['subscribers' => $subscribers]);

        return $subscribers;
    }

    /** @return array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    public function getAllSubscribers(): array
    {
        return $this->subscriberRegistry->all();
    }

    public function hasSubscriber(string $event): bool
    {
        return $this->getSubscriber($event) !== [];
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function addEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $this->subscriberRegistry->add($events, $subscriberFQCN);
    }

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function removeEventSubscriber(string|array $events, string $subscriberFQCN): void
    {
        $this->subscriberRegistry->remove($events, $subscriberFQCN);
    }
}
