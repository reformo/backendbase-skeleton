<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Query;

use Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers\GetExamplesByGroupHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\Primitives\Pagination;

/** @implements Query<ExamplePage> */
#[CQRSHandler(GetExamplesByGroupHandler::class)]
readonly class GetExamplesByGroup implements Query
{
    public function __construct(private ExampleType $type, private int|null $typeTargetId, private string $group, private Pagination $pagination)
    {
    }

    public function type(): ExampleType
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
