<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\IntegrationEventSubscribers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\IntegrationEvents\NewExampleAdded;
use Backendbase\Shared\Domain\Messaging\IntegrationEvent;
use Backendbase\Shared\Domain\Messaging\IntegrationEventSubscriber;
use Override;
use Psr\Log\LoggerInterface;

readonly class NewExampleAddedSubscriber implements IntegrationEventSubscriber
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** @param NewExampleAdded $integrationEvent */
    #[Override]
    public function handle(IntegrationEvent $integrationEvent): void
    {
        $this->logger->debug('NewExampleAddedSubscriber', $integrationEvent->toArray());
    }

    /** @return array<int, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [NewExampleAdded::EVENT_TYPE];
    }
}
