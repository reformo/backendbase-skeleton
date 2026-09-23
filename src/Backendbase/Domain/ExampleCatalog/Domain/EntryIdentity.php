<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

/**
 * @phpstan-type EntryIdentityState array{
 *     type: EntryType,
 *     typeTargetId: int|null,
 *     group: string,
 *     key: string
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
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'key' => $key,
        ];
    }

    public function type(): EntryType
    {
        return $this->state['type'];
    }

    public function typeTargetId(): int|null
    {
        return $this->state['typeTargetId'];
    }

    public function group(): string
    {
        return $this->state['group'];
    }

    public function key(): string
    {
        return $this->state['key'];
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
