<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryGroup;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryKey;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryTargetId;

final readonly class EntryIdentity
{
    private EntryType $entryType;
    private EntryTargetId $targetId;
    private EntryGroup $entryGroup;
    private EntryKey $entryKey;

    public function __construct(EntryType $type, int|null $typeTargetId, string $group, string $key)
    {
        $this->entryType  = $type;
        $this->targetId   = new EntryTargetId($typeTargetId);
        $this->entryGroup = new EntryGroup($group);
        $this->entryKey   = new EntryKey($key);
    }

    public function type(): EntryType
    {
        return $this->entryType;
    }

    public function typeTargetId(): int|null
    {
        $value = $this->targetId;

        return $value->toInteger();
    }

    public function group(): string
    {
        $value = $this->entryGroup;

        return $value->toString();
    }

    public function key(): string
    {
        $value = $this->entryKey;

        return $value->toString();
    }

    /** @return array{type: string, typeTargetId: int|null, group: string, key: string} */
    public function toArray(): array
    {
        $type         = $this->type();
        $typeTargetId = $this->typeTargetId();
        $group        = $this->group();
        $key          = $this->key();

        return [
            'type' => $type->value,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'key' => $key,
        ];
    }
}
