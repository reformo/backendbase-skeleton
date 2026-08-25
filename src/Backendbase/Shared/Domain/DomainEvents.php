<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

use Doctrine\Common\Collections\ArrayCollection;

use function array_values;

/** @extends ArrayCollection<int, DomainEvent> */
class DomainEvents extends ArrayCollection
{
    /** @return list<DomainEvent> */
    public function events(): array
    {
        return array_values($this->toArray());
    }
}
