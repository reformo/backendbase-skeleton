<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Identifier;

use Backendbase\Shared\Domain\Identifier\EntityObjectId as EntityObjectIdInterface;
use MongoDB\BSON\ObjectId;
use Override;

abstract class EntityObjectId implements EntityObjectIdInterface
{
    final public function __construct(private readonly string $id)
    {
    }

    #[Override]
    public static function generate(): static
    {
        return new static((string) new ObjectId());
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
