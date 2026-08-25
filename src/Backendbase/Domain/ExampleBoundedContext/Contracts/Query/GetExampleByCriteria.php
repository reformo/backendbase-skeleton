<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Query;

use Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers\GetExampleByCriteriaHandler;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<ExampleDetails|null> */
#[CQRSHandler(GetExampleByCriteriaHandler::class)]
class GetExampleByCriteria implements Query
{
    public function __construct(private ExampleType $type, private int|null $typeTargetId, private string $group, private string $key)
    {
    }

    public function group(): string
    {
        return $this->group;
    }

    public function type(): ExampleType
    {
        return $this->type;
    }

    public function typeTargetId(): int|null
    {
        return $this->typeTargetId;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array{type: string, typeTargetId: int|null, group: string, key: string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'typeTargetId' => $this->typeTargetId,
            'group' => $this->group,
            'key' => $this->key,
        ];
    }
}
