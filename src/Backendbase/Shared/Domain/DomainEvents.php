<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

use function count;

/** @implements IteratorAggregate<int, DomainEvent> */
final class DomainEvents implements Countable, IteratorAggregate
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function add(DomainEvent $domainEvent): void
    {
        $this->events[] = $domainEvent;
    }

    public function first(): DomainEvent|null
    {
        return $this->events[0] ?? null;
    }

    /** @return list<DomainEvent> */
    public function events(): array
    {
        return $this->events;
    }

    #[Override]
    public function count(): int
    {
        return count($this->events);
    }

    /** @return Traversable<int, DomainEvent> */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }
}
