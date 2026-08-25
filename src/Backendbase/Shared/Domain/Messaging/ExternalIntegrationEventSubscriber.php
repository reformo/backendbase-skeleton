<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

interface ExternalIntegrationEventSubscriber
{
    /** @return array<int, string> */
    public static function getSubscribedEvents(): array;

    public function handle(EventMessage $message): void;
}
