<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Query;

use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\Primitives\Pagination;

/** @implements Query<EntryGroupPage> */
readonly class GetEntryGroupsByType implements Query
{
    /** @var array{type: EntryType, typeTargetId: int|null} */
    private array $criteria;

    public function __construct(
        EntryType $type,
        int|null $typeTargetId,
        private Pagination $pagination = new Pagination(1000, 1),
    ) {
        $this->criteria = ['type' => $type, 'typeTargetId' => $typeTargetId];
    }

    public function type(): EntryType
    {
        return $this->criteria['type'];
    }

    public function typeTargetId(): int|null
    {
        return $this->criteria['typeTargetId'];
    }

    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array{type: string, typeTargetId: int|null, pagination: Pagination} */
    public function toArray(): array
    {
        return [
            'type' => $this->criteria['type']->value,
            'typeTargetId' => $this->criteria['typeTargetId'],
            'pagination' => $this->pagination,
        ];
    }
}
