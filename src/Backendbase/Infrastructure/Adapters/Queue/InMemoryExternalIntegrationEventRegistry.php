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

    /** @param iterable<int, array{eventName: string, eventVersion: string, messageFQCN: class-string}> $definitions */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->register(
                $definition['eventName'],
                $definition['eventVersion'],
                $definition['messageFQCN'],
            );
        }
    }

    /** @param class-string $messageFQCN */
    private function register(string $eventName, string $eventVersion, string $messageFQCN): void
    {
        if (! is_a($messageFQCN, EventMessage::class, true)) {
            throw new InvalidArgumentException($messageFQCN . ' is not an event message class.');
        }

        $key = self::key($eventName, $eventVersion);
        if (isset($this->messageClasses[$key])) {
            throw new InvalidArgumentException(
                'An external integration event is already registered for ' . $eventName . ' ' . $eventVersion . '.',
            );
        }

        $this->messageClasses[$key] = $messageFQCN;
    }

    public function messageClass(string $eventName, string $eventVersion): string
    {
        $messageFQCN = $this->messageClasses[self::key($eventName, $eventVersion)] ?? null;
        if ($messageFQCN === null) {
            throw new UnexpectedValueException(
                'No message contract is registered for ' . $eventName . ' ' . $eventVersion . '.',
            );
        }

        return $messageFQCN;
    }

    private static function key(string $eventName, string $eventVersion): string
    {
        return $eventName . ':' . $eventVersion;
    }
}
