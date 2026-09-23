<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\ReadModel;

use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use DateTimeImmutable;

final readonly class EntryDetails
{
    /**
     * @var array{
     *     uuid: string,
     *     type: EntryType,
     *     typeTargetId: int|null,
     *     group: string,
     *     lookupKey: string,
     *     lookupValue: string,
     *     details: array<string, mixed>,
     *     isActive: bool,
     *     updatedAt: DateTimeImmutable,
     *     createdAt: DateTimeImmutable
     * }
     */
    private array $data;

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
        DateTimeImmutable $updatedAt,
        DateTimeImmutable $createdAt,
    ) {
        $this->data = [
            'uuid' => $uuid,
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'lookupKey' => $lookupKey,
            'lookupValue' => $lookupValue,
            'details' => $details,
            'isActive' => $isActive,
            'updatedAt' => $updatedAt,
            'createdAt' => $createdAt,
        ];
    }

    public function uuid(): string
    {
        return $this->data['uuid'];
    }

    public function type(): EntryType
    {
        return $this->data['type'];
    }

    public function typeTargetId(): int|null
    {
        return $this->data['typeTargetId'];
    }

    public function group(): string
    {
        return $this->data['group'];
    }

    public function lookupKey(): string
    {
        return $this->data['lookupKey'];
    }

    public function lookupValue(): string
    {
        return $this->data['lookupValue'];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->data['details'];
    }

    public function isActive(): bool
    {
        return $this->data['isActive'];
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->data['updatedAt'];
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->data['createdAt'];
    }
}
