<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Identifier;

use Backendbase\Shared\Domain\Identifier\EntityId as EntityIdInterface;
use Backendbase\Shared\Exception\InvalidResourceId;
use JsonSerializable;
use Override;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Throwable;

abstract class EntityId implements EntityIdInterface, JsonSerializable, Stringable
{
    final public function __construct(private readonly UuidInterface $id)
    {
    }

    #[Override]
    public static function create(): static
    {
        return new static(RamseyUuid::uuid7());
    }

    public static function generate(): static
    {
        return new static(RamseyUuid::uuid7());
    }

    public static function null(): static
    {
        return new static(RamseyUuid::fromString('00000000-0000-0000-0000-000000000000'));
    }

    #[Override]
    public static function fromString(string $uuid): static
    {
        try {
            return new static(RamseyUuid::fromString($uuid));
        } catch (Throwable $exception) {
            throw InvalidResourceId::create('Invalid uuid', ['exceptionDetails' => $exception->getMessage()]);
        }
    }

    public function id(): string
    {
        return $this->id->toString();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id();
    }

    #[Override]
    public function toString(): string
    {
        return $this->id();
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->id();
    }
}
