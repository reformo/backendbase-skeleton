<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

use JsonSerializable;

interface EventMessage extends JsonSerializable
{
    public const array INPUT_FIELD_ALIASES = [];

    /** @return array<string, mixed> */
    public function toArray(): array;
}
