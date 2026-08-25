<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

use JsonSerializable;

interface Command extends JsonSerializable
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
