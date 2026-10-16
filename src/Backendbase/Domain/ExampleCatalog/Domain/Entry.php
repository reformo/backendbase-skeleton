<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryGroup;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryId;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryKey;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryTargetId;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryValue;
use DateTimeImmutable;

/** @phpstan-import-type EntryState from EntrySnapshot */
final class Entry
{
    /** @param EntryState $state */
    private function __construct(private array $state)
    {
    }

    /** @param array<array-key, mixed> $details */
    public static function create(
        string $entryId,
        EntryType $type,
        int|null $typeTargetId,
        string $group,
        bool $isActive,
        string $key,
        string $value,
        array $details,
        DateTimeImmutable $now,
    ): self {
        return self::reconstitute($entryId, $type, $typeTargetId, $group, $key, $value, $details, $isActive, $now, $now, null);
    }

    /** @param array<array-key, mixed> $details */
    public static function reconstitute(
        string $entryId,
        EntryType $type,
        int|null $typeTargetId,
        string $group,
        string $key,
        string $value,
        array $details,
        bool $isActive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        DateTimeImmutable|null $removedAt,
    ): self {
        return new self([
            'uuid' => EntryId::fromString($entryId),
            'type' => $type,
            'typeTargetId' => new EntryTargetId($typeTargetId),
            'group' => new EntryGroup($group),
            'lookupKey' => new EntryKey($key),
            'lookupValue' => new EntryValue($value),
            'details' => new EntryDetails($details),
            'isActive' => $isActive,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'removedAt' => $removedAt,
        ]);
    }

    /** @param array<array-key, mixed>|null $details */
    public function change(bool|null $isActive, string|null $value, array|null $details, DateTimeImmutable $now): void
    {
        $validatedValue   = $value === null ? $this->state['lookupValue'] : new EntryValue($value);
        $validatedDetails = $details === null ? $this->state['details'] : new EntryDetails($details);

        if ($isActive !== null) {
            $this->state['isActive'] = $isActive;
        }

        $this->state['lookupValue'] = $validatedValue;
        $this->state['details']     = $validatedDetails;

        $this->state['updatedAt'] = $now;
    }

    public function remove(DateTimeImmutable $now): void
    {
        $this->state['removedAt'] = $now;
        $this->state['updatedAt'] = $now;
    }

    public function id(): string
    {
        $identifier = $this->state['uuid'];

        return $identifier->id();
    }

    public function isRemoved(): bool
    {
        return $this->state['removedAt'] !== null;
    }

    public function hasIdentity(EntryIdentity $identity): bool
    {
        if ($this->state['type'] !== $identity->type()) {
            return false;
        }

        $targetId    = $this->state['typeTargetId'];
        $targetValue = $targetId->toInteger();
        if ($targetValue !== $identity->typeTargetId()) {
            return false;
        }

        $group      = $this->state['group'];
        $groupValue = $group->toString();
        if ($groupValue !== $identity->group()) {
            return false;
        }

        $key      = $this->state['lookupKey'];
        $keyValue = $key->toString();

        return $keyValue === $identity->key();
    }

    public function snapshot(): EntrySnapshot
    {
        return new EntrySnapshot(
            $this->state['uuid'],
            $this->state['type'],
            $this->state['typeTargetId'],
            $this->state['group'],
            $this->state['lookupKey'],
            $this->state['lookupValue'],
            $this->state['details'],
            $this->state['isActive'],
            $this->state['createdAt'],
            $this->state['updatedAt'],
            $this->state['removedAt'],
        );
    }
}
