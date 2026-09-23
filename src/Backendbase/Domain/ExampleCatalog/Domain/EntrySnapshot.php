<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

use DateTimeImmutable;

/**
 * @phpstan-type EntryState array{
 *     uuid: string,
 *     type: EntryType,
 *     typeTargetId: int|null,
 *     group: string,
 *     lookupKey: string,
 *     lookupValue: string,
 *     details: array<string, mixed>,
 *     isActive: bool,
 *     createdAt: DateTimeImmutable,
 *     updatedAt: DateTimeImmutable,
 *     removedAt: DateTimeImmutable|null
 * }
 */
final readonly class EntrySnapshot
{
    /** @var EntryState */
    private array $values;

    /** @param array<string, mixed> $details */
    public function __construct(
        string $uuid,
        EntryType $type,
        int|null $typeTargetId,
        string $group,
        string $lookupKey,
        string $lookupValue,
        array $details,
        bool $isActive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        DateTimeImmutable|null $removedAt,
    ) {
        $this->values = [
            'uuid' => $uuid,
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'lookupKey' => $lookupKey,
            'lookupValue' => $lookupValue,
            'details' => $details,
            'isActive' => $isActive,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'removedAt' => $removedAt,
        ];
    }

    public function uuid(): string
    {
        return $this->values['uuid'];
    }

    public function type(): EntryType
    {
        return $this->values['type'];
    }

    public function typeTargetId(): int|null
    {
        return $this->values['typeTargetId'];
    }

    public function group(): string
    {
        return $this->values['group'];
    }

    public function lookupKey(): string
    {
        return $this->values['lookupKey'];
    }

    public function lookupValue(): string
    {
        return $this->values['lookupValue'];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->values['details'];
    }

    public function isActive(): bool
    {
        return $this->values['isActive'];
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->values['createdAt'];
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->values['updatedAt'];
    }

    public function removedAt(): DateTimeImmutable|null
    {
        return $this->values['removedAt'];
    }
}
