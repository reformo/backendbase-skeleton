<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Domain;

/**
 * @phpstan-type ExampleIdentityState array{
 *     type: ExampleType,
 *     typeTargetId: int|null,
 *     group: string,
 *     key: string
 * }
 */
final readonly class ExampleIdentity
{
    /** @var ExampleIdentityState */
    private array $state;

    public function __construct(ExampleType $type, int|null $typeTargetId, string $group, string $key)
    {
        $this->state = [
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'key' => $key,
        ];
    }

    public function type(): ExampleType
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
