<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

use DateTimeImmutable;
use JsonSerializable;

interface IntegrationEvent extends JsonSerializable
{
    public function occurredOn(): DateTimeImmutable;

    public function eventName(): string;

    public function eventVersion(): string;

    /** @return array<string, mixed> */
    public function getEventArguments(): array;

    public function isExternal(): bool;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
