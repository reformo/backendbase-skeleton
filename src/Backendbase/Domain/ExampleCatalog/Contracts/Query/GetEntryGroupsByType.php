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
    public function __construct(
        private EntryType $type,
        private int|null $typeTargetId,
        private Pagination $pagination = new Pagination(1000, 1),
    ) {
    }

    public function type(): EntryType
    {
        return $this->type;
    }

    public function typeTargetId(): int|null
    {
        return $this->typeTargetId;
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
        $type = $this->type;

        return [
            'type' => $type->value,
            'typeTargetId' => $this->typeTargetId,
            'pagination' => $this->pagination,
        ];
    }
}
