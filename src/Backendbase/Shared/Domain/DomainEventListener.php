<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

interface DomainEventListener
{
    public function handle(DomainEvent $domainEvent): void;
}
