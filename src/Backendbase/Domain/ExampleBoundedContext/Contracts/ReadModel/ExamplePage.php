<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel;

final readonly class ExamplePage
{
    /** @param list<ExampleListItem> $items */
    public function __construct(private array $items, private int $total)
    {
    }

    /** @return list<ExampleListItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }
}
