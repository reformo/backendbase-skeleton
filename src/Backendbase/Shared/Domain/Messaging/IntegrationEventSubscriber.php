<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

interface IntegrationEventSubscriber
{
    /** @return array<int, string> */
    public static function getSubscribedEvents(): array;

    public function handle(IntegrationEvent $integrationEvent): void;
}
