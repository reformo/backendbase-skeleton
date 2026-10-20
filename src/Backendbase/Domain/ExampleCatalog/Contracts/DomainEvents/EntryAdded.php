<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents;

use Backendbase\Domain\ExampleCatalog\Application\DomainEventListener\EntryAddedListener;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Shared\Domain\Attributes\DomainEventListener as DomainEventListenerAttribute;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;
use DateTimeImmutable;

#[DomainEventListenerAttribute(EntryAddedListener::class)]
class EntryAdded implements DomainEvent
{
    use DomainEventTrait;

    public function __construct(
        private AddEntry $payload,
        DateTimeImmutable $occurredOn,
    ) {
        $this->initializeOccurredOn($occurredOn);
    }

    public function entryId(): string
    {
        $payload = $this->payload;

        return $payload->entryId();
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
        $entryId = $this->entryId();
        $payload = $this->payload;

        return ['exampleId' => $entryId, 'payload' => $payload->toArray()];
    }
}
