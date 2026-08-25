<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Domain\Messaging\ExternalIntegrationEventSubscriber;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Services\EventManager\EventManager;
use CuyZ\Valinor\MapperBuilder;
use UnexpectedValueException;

use function is_a;
use function is_array;

final readonly class ExternalIntegrationEventDispatcher
{
    public function __construct(
        private EventManager $eventManager,
        private ExternalIntegrationEventRegistry $registry,
    ) {
    }

    public function dispatch(string $eventName, string $eventVersion, mixed $payload): void
    {
        $subscribers = $this->eventManager->getSubscriber($eventName);
        if ($subscribers === []) {
            throw new UnexpectedValueException('No subscriber is registered for ' . $eventName . '.');
        }

        foreach ($subscribers as $subscriberFQCN) {
            if (! is_a($subscriberFQCN, ExternalIntegrationEventSubscriber::class, true)) {
                throw new UnexpectedValueException($subscriberFQCN . ' is not an external event subscriber.');
            }
        }

        if (! is_array($payload)) {
            throw new UnexpectedValueException('The queue message data must be an object.');
        }

        $messageFQCN = $this->registry->messageClass($eventName, $eventVersion);
        $message     = new MapperBuilder()
            ->allowPermissiveTypes()
            ->mapper()
            ->map($messageFQCN, $payload);
        $this->eventManager->dispatchExternalEvent($eventName, $message);
    }
}
