<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use InvalidArgumentException;
use UnexpectedValueException;

use function is_a;

final class InMemoryExternalIntegrationEventRegistry implements ExternalIntegrationEventRegistry
{
    /** @var array<string, class-string<EventMessage>> */
    private array $messageClasses = [];

    /** @param iterable<int, array{'event-name': string, 'event-version': string, 'message-fqcn': class-string}> $definitions */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->register(
                $definition['event-name'],
                $definition['event-version'],
                $definition['message-fqcn'],
            );
        }
    }

    /** @param class-string $messageClass */
    private function register(string $eventName, string $eventVersion, string $messageClass): void
    {
        if (! is_a($messageClass, EventMessage::class, true)) {
            throw new InvalidArgumentException($messageClass . ' is not an event message class.');
        }

        $key = self::key($eventName, $eventVersion);
        if (isset($this->messageClasses[$key])) {
            throw new InvalidArgumentException(
                'An external integration event is already registered for ' . $eventName . ' ' . $eventVersion . '.',
            );
        }

        $this->messageClasses[$key] = $messageClass;
    }

    public function messageClass(string $eventName, string $eventVersion): string
    {
        $messageClass = $this->messageClasses[self::key($eventName, $eventVersion)] ?? null;
        if ($messageClass === null) {
            throw new UnexpectedValueException(
                'No message contract is registered for ' . $eventName . ' ' . $eventVersion . '.',
            );
        }

        return $messageClass;
    }

    private static function key(string $eventName, string $eventVersion): string
    {
        return $eventName . ':' . $eventVersion;
    }
}
