<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Domain\Messaging\EventMessage;

interface ExternalIntegrationEventRegistry
{
    /** @return class-string<EventMessage> */
    public function messageClass(string $eventName, string $eventVersion): string;
}
