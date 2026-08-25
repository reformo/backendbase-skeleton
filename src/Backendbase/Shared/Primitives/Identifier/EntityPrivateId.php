<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Identifier;

use JsonSerializable;
use Override;

abstract class EntityPrivateId implements JsonSerializable
{
    final public function __construct(private readonly int $id)
    {
    }

    public static function fromValue(int $id): static
    {
        return new static($id);
    }

    public function id(): int
    {
        return $this->id;
    }

    #[Override]
    public function jsonSerialize(): int
    {
        return $this->id();
    }
}
