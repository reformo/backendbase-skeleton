<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

interface DomainEventPublisher
{
    public function publish(DomainEvent $domainEvent): void;
}
