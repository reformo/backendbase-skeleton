<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services\EventManager;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;

interface EventSubscriber
{
    /** @return array<int, string> */
    public static function getSubscribedEvents(): array;

    public function handle(IntegrationEvent $args): void;
}
