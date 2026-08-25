<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\ExternalIntegrationEventSubscribers\ExampleBoundedContext;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1\NewExampleAddedMessage;
use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Override;
use Psr\Log\LoggerInterface;

readonly class NewExampleAddedExternalSubscriber implements ExternalIntegrationEventSubscriber
{
    public const string EVENT_TYPE = 'Example_NewExampleAdded_Event';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** @param NewExampleAddedMessage $message */
    #[Override]
    public function handle(EventMessage $message): void
    {
        $this->logger->debug('NewExampleAddedExternalSubscriber', $message->toArray());
        $this->logger->debug('External Done');
    }

    /** @return array<int, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [self::EVENT_TYPE];
    }
}
