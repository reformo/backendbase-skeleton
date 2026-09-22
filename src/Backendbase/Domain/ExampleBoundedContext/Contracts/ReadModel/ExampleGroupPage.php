<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel;

final readonly class ExampleGroupPage
{
    /** @param list<string> $items */
    public function __construct(private array $items, private int $total)
    {
    }

    /** @return list<string> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }
}
