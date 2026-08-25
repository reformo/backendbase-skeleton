<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Domain;

use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;

/**
 * @phpstan-type ExampleState array{
 *     uuid: string,
 *     type: ExampleType,
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
final class Example
{
    /** @param ExampleState $state */
    private function __construct(private array $state)
    {
    }

    /** @param array<string, mixed> $details */
    public static function create(
        string $exampleId,
        ExampleType $type,
        int|null $typeTargetId,
        string $group,
        bool $isActive,
        string $key,
        string $value,
        array $details,
    ): self {
        $now = DateTimeImmutableFactory::create();

        return new self([
            'uuid' => $exampleId,
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'lookupKey' => $key,
            'lookupValue' => $value,
            'details' => $details,
            'isActive' => $isActive,
            'createdAt' => $now,
            'updatedAt' => $now,
            'removedAt' => null,
        ]);
    }

    /** @param array<string, mixed> $details */
    public static function reconstitute(
        string $exampleId,
        ExampleType $type,
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
            'uuid' => $exampleId,
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'lookupKey' => $key,
            'lookupValue' => $value,
            'details' => $details,
            'isActive' => $isActive,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'removedAt' => $removedAt,
        ]);
    }

    /** @param array<string, mixed>|null $details */
    public function change(bool|null $isActive, string|null $value, array|null $details): void
    {
        if ($isActive !== null) {
            $this->state['isActive'] = $isActive;
        }

        if ($value !== null) {
            $this->state['lookupValue'] = $value;
        }

        if ($details !== null) {
            $this->state['details'] = $details;
        }

        $this->state['updatedAt'] = DateTimeImmutableFactory::create();
    }

    public function remove(): void
    {
        $now                      = DateTimeImmutableFactory::create();
        $this->state['removedAt'] = $now;
        $this->state['updatedAt'] = $now;
    }

    public function id(): string
    {
        return $this->state['uuid'];
    }

    public function isRemoved(): bool
    {
        return $this->state['removedAt'] !== null;
    }

    /** @return ExampleState */
    public function snapshot(): array
    {
        return $this->state;
    }
}
