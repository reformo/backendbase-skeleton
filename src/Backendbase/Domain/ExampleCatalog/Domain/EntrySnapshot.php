<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryGroup;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryId;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryKey;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryTargetId;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryValue;
use DateTimeImmutable;

/**
 * @phpstan-type EntryState array{
 *     uuid: EntryId,
 *     type: EntryType,
 *     typeTargetId: EntryTargetId,
 *     group: EntryGroup,
 *     lookupKey: EntryKey,
 *     lookupValue: EntryValue,
 *     details: EntryDetails,
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

    public function __construct(
        EntryId $uuid,
        EntryType $type,
        EntryTargetId $typeTargetId,
        EntryGroup $group,
        EntryKey $lookupKey,
        EntryValue $lookupValue,
        EntryDetails $details,
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
        $value = $this->values['uuid'];

        return $value->id();
    }

    public function type(): EntryType
    {
        return $this->values['type'];
    }

    public function typeTargetId(): int|null
    {
        $value = $this->values['typeTargetId'];

        return $value->toInteger();
    }

    public function group(): string
    {
        $value = $this->values['group'];

        return $value->toString();
    }

    public function lookupKey(): string
    {
        $value = $this->values['lookupKey'];

        return $value->toString();
    }

    public function lookupValue(): string
    {
        $value = $this->values['lookupValue'];

        return $value->toString();
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        $value = $this->values['details'];

        return $value->toArray();
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

    public function compareCreationOrder(self $other): int
    {
        $createdAt       = $this->createdAt();
        $otherCreatedAt  = $other->createdAt();
        $identifier      = $this->uuid();
        $otherIdentifier = $other->uuid();

        return [$createdAt, $identifier] <=> [$otherCreatedAt, $otherIdentifier];
    }

    public function removedAt(): DateTimeImmutable|null
    {
        return $this->values['removedAt'];
    }
}
