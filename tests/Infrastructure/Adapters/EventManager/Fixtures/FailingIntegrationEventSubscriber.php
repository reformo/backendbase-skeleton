<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager\Fixtures;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use RuntimeException;

final readonly class FailingIntegrationEventSubscriber implements IntegrationEventSubscriber
{
    public function __construct()
    {
    }

    /** @return array<int, string> */
    public static function getSubscribedEvents(): array
    {
        return [EntryRemoved::EVENT_TYPE];
    }

    public function handle(IntegrationEvent $integrationEvent): void
    {
        throw new RuntimeException('The local integration subscriber failed.');
    }
}
