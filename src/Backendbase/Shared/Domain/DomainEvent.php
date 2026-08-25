<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

use DateTimeImmutable;
use JsonSerializable;

interface DomainEvent extends JsonSerializable
{
    public function occurredOn(): DateTimeImmutable;

    public function eventName(): string;

    /** @return array<string, mixed> */
    public function getEventArguments(): array;
}
