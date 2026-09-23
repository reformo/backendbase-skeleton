<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services\EventManager;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;

interface EventManager
{
    /** Run local subscribers and optionally append to the outbox inside the active database transaction. */
    public function dispatchEvent(IntegrationEvent $event): void;

    /** Run queue subscribers without publishing the received event again. */
    public function dispatchExternalEvent(string $eventName, EventMessage $message): void;

    /** @return array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>> */
    public function getSubscriber(string $event): array;

    /** @return array<string, array<string, class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber>>> */
    public function getAllSubscribers(): array;

    public function hasSubscriber(string $event): bool;

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function addEventSubscriber(string|array $events, string $subscriberFQCN): void;

    /**
     * @param string|array<int, string>                                                   $events
     * @param class-string<IntegrationEventSubscriber|ExternalIntegrationEventSubscriber> $subscriberFQCN
     */
    public function removeEventSubscriber(string|array $events, string $subscriberFQCN): void;
}
