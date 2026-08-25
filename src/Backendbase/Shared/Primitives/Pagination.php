<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use JsonSerializable;
use Override;

use function ceil;

class Pagination implements JsonSerializable
{
    private int $total      = 0;
    private int $totalPages = 0;

    public function __construct(private readonly int $pageSize, private int $page)
    {
    }

    public function page(): int
    {
        return $this->page > 0 ? $this->page : 1;
    }

    public function pageSize(): int
    {
        return $this->pageSize;
    }

    public function setTotal(int $total): void
    {
        $this->total      = $total;
        $this->totalPages = (int) ceil($total / $this->pageSize);
        if ($this->page < 0) {
            $this->page = 1;
        }

        if ($this->page <= $this->totalPages) {
            return;
        }

        $this->page = $this->totalPages;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function totalPages(): int
    {
        return $this->totalPages;
    }

    public function getCurrentPage(): int
    {
        return $this->page();
    }

    public function getOffset(): int
    {
        return ($this->getCurrentPage() - 1) * $this->pageSize();
    }

    /** @return array{pageSize: int, page: int, total: int} */
    public function toArray(): iterable
    {
        return [
            'pageSize' => $this->pageSize(),
            'page' => $this->page(),
            'total' => $this->total(),
        ];
    }

    /** @return array{pageSize: int, page: int, total: int} */
    #[Override]
    public function jsonSerialize(): iterable
    {
        return $this->toArray();
    }
}
