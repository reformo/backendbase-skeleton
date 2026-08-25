<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\DomainEvents;

use Backendbase\Domain\ExampleBoundedContext\Application\DomainEventListener\ExampleAddedListener;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Shared\Domain\Attributes\DomainEventListener as DomainEventListenerAttribute;
use Backendbase\Shared\Domain\DomainEvent;
use Backendbase\Shared\Domain\DomainEventTrait;

#[DomainEventListenerAttribute(ExampleAddedListener::class)]
class ExampleAdded implements DomainEvent
{
    use DomainEventTrait;

    public function __construct(
        private string $exampleId,
        private AddNewExample $payload,
    ) {
        $this->initializeOccurredOn();
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }

    public function payload(): AddNewExample
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
            'exampleId' => $this->exampleId,
            'payload' => $this->payload->toArray(),
        ];
    }
}
