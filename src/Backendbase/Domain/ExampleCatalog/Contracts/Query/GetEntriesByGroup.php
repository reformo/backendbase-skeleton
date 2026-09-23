<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Query;

use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntriesByGroupHandler;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\Primitives\Pagination;

/** @implements Query<EntryPage> */
#[CQRSHandler(GetEntriesByGroupHandler::class)]
readonly class GetEntriesByGroup implements Query
{
    public function __construct(private EntryType $type, private int|null $typeTargetId, private string $group, private Pagination $pagination)
    {
    }

    public function type(): EntryType
    {
        return $this->type;
    }

    public function typeTargetId(): int|null
    {
        return $this->typeTargetId;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array{type: string, typeTargetId: int|null, group: string, pagination: Pagination} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'typeTargetId' => $this->typeTargetId,
            'group' => $this->group,
            'pagination' => $this->pagination,
        ];
    }
}
