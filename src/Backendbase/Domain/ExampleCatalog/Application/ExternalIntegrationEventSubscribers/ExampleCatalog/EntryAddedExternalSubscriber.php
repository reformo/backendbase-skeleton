<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog;

use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Override;
use Psr\Log\LoggerInterface;

readonly class EntryAddedExternalSubscriber implements ExternalIntegrationEventSubscriber
{
    public const string EVENT_TYPE = 'Example_NewExampleAdded_Event';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** @param EntryAddedMessage $message */
    #[Override]
    public function handle(EventMessage $message): void
    {
        $this->logger->debug('EntryAddedExternalSubscriber', $message->toArray());
        $this->logger->debug('External Done');
    }

    /** @return array<int, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [self::EVENT_TYPE];
    }
}
