<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

abstract class Aggregate
{
    private DomainEvents|null $events = null;

    public function recordEvent(DomainEvent $domainEvent): void
    {
        if ($this->events === null) {
            $this->events = new DomainEvents();
        }

        $this->events->add($domainEvent);
    }

    public function getRecordedEvents(): DomainEvents
    {
        if ($this->events === null) {
            $this->events = new DomainEvents();
        }

        return $this->events;
    }
}
