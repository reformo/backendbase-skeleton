<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Query;

use Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers\GetExampleGroupsByTypeHandler;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<list<string>> */
#[CQRSHandler(GetExampleGroupsByTypeHandler::class)]
readonly class GetExampleGroupsByType implements Query
{
    public function __construct(private ExampleType $type, private int|null $typeTargetId)
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

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array{type: string, typeTargetId: int|null} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'typeTargetId' => $this->typeTargetId,
        ];
    }
}
