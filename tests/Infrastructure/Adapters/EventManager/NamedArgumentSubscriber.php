<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Override;

final readonly class NamedArgumentSubscriber implements IntegrationEventSubscriber
{
    public function __construct(private string $subscriberName)
    {
    }

    public function subscriberName(): string
    {
        return $this->subscriberName;
    }

    /** @return array<int, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [];
    }

    #[Override]
    public function handle(IntegrationEvent $integrationEvent): void
    {
    }
}
