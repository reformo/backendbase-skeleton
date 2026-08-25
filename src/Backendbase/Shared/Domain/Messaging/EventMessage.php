<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

use JsonSerializable;

interface EventMessage extends JsonSerializable
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
