<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryGroup;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryKey;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryTargetId;

/**
 * @phpstan-type EntryIdentityState array{
 *     type: EntryType,
 *     typeTargetId: EntryTargetId,
 *     group: EntryGroup,
 *     key: EntryKey
 * }
 */
final readonly class EntryIdentity
{
    /** @var EntryIdentityState */
    private array $state;

    public function __construct(EntryType $type, int|null $typeTargetId, string $group, string $key)
    {
        $this->state = [
            'type' => $type,
            'typeTargetId' => new EntryTargetId($typeTargetId),
            'group' => new EntryGroup($group),
            'key' => new EntryKey($key),
        ];
    }

    public function type(): EntryType
    {
        return $this->state['type'];
    }

    public function typeTargetId(): int|null
    {
        $value = $this->state['typeTargetId'];

        return $value->toInteger();
    }

    public function group(): string
    {
        $value = $this->state['group'];

        return $value->toString();
    }

    public function key(): string
    {
        $value = $this->state['key'];

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
