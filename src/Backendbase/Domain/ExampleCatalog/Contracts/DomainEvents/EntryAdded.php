<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents;

use Backendbase\Domain\ExampleCatalog\Application\DomainEventListener\EntryAddedListener;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Shared\Domain\Attributes\DomainEventListener as DomainEventListenerAttribute;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;

#[DomainEventListenerAttribute(EntryAddedListener::class)]
class EntryAdded implements DomainEvent
{
    use DomainEventTrait;

    public function __construct(
        private string $entryId,
        private AddEntry $payload,
    ) {
        $this->initializeOccurredOn();
    }

    public function entryId(): string
    {
        return $this->entryId;
    }

    public function payload(): AddEntry
    {
        return $this->payload;
    }

    public function eventName(): string
    {
        return self::class;
    }

    /** @return array<string, mixed> */
    public function getEventArguments(): array
    {
        return [
            'exampleId' => $this->entryId,
            'payload' => $this->payload->toArray(),
        ];
    }
}
