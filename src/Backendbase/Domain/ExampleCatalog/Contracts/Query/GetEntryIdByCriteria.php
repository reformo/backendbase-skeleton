<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Query;

use Backendbase\Domain\ExampleCatalog\Application\QueryHandlers\GetEntryIdByCriteriaHandler;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<string|null> */
#[CQRSHandler(GetEntryIdByCriteriaHandler::class)]
readonly class GetEntryIdByCriteria implements Query
{
    public function __construct(private EntryType $type, private int|null $typeTargetId, private string $group, private string $key)
    {
    }

    public function group(): string
    {
        return $this->group;
    }

    public function type(): EntryType
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
