<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;

trait DomainEventTrait
{
    private DateTimeImmutable $occurredOn;

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    protected function initializeOccurredOn(): void
    {
        $this->occurredOn = DateTimeImmutableFactory::create();
    }

    public function jsonSerialize(): mixed
    {
        return $this->getEventArguments();
    }

    /** @return array<string, mixed> */
    abstract public function getEventArguments(): array;
}
