<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\ReadModel;

use DateTimeImmutable;

final readonly class EntryListItem
{
    /**
     * @var array{
     *     uuid: string,
     *     lookupKey: string,
     *     lookupValue: string,
     *     details: array<string, mixed>,
     *     isActive: bool,
     *     createdAt: DateTimeImmutable
     * }
     */
    private array $data;

    /** @param array<string, mixed> $details */
    public function __construct(
        string $uuid,
        string $lookupKey,
        string $lookupValue,
        array $details,
        bool $isActive,
        DateTimeImmutable $createdAt,
    ) {
        $this->data = [
            'uuid' => $uuid,
            'lookupKey' => $lookupKey,
            'lookupValue' => $lookupValue,
            'details' => $details,
            'isActive' => $isActive,
            'createdAt' => $createdAt,
        ];
    }

    public function uuid(): string
    {
        return $this->data['uuid'];
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

    public function createdAt(): DateTimeImmutable
    {
        return $this->data['createdAt'];
    }
}
