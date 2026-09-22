<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Query;

use Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers\GetExampleGroupsByTypeHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleGroupPage;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\Primitives\Pagination;

/** @implements Query<ExampleGroupPage> */
#[CQRSHandler(GetExampleGroupsByTypeHandler::class)]
readonly class GetExampleGroupsByType implements Query
{
    /** @var array{type: ExampleType, typeTargetId: int|null} */
    private array $criteria;

    public function __construct(
        ExampleType $type,
        int|null $typeTargetId,
        private Pagination $pagination = new Pagination(1000, 1),
    ) {
        $this->criteria = ['type' => $type, 'typeTargetId' => $typeTargetId];
    }

    public function type(): ExampleType
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
