<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Override;
use Traversable;

use function count;

/** @implements IteratorAggregate<array-key, mixed> */
class JsonSerializableArrayCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @param array<array-key, mixed> $items */
    public function __construct(private array $items = [])
    {
    }

    /** @return array<array-key, mixed> */
    public function toArray(): array
    {
        return $this->items;
    }

    #[Override]
    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<array-key, mixed> */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
