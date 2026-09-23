<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\IntegrationEventSubscribers;

use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Override;
use Psr\Log\LoggerInterface;

readonly class EntryAddedSubscriber implements IntegrationEventSubscriber
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** @param EntryAdded $integrationEvent */
    #[Override]
    public function handle(IntegrationEvent $integrationEvent): void
    {
        $this->logger->debug('EntryAddedSubscriber', $integrationEvent->toArray());
    }

    /** @return array<int, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [EntryAdded::EVENT_TYPE];
    }
}
