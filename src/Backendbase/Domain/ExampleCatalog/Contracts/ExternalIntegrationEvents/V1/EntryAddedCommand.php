<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1;

final readonly class EntryAddedCommand
{
    /**
     * @var array{
     *     exampleId: string,
     *     type: string,
     *     typeTargetId: int|null,
     *     group: string,
     *     isActive: bool,
     *     key: string,
     *     value: string,
     *     details: array<string, mixed>
     * }
     */
    private array $data;

    /** @param array<string, mixed> $details */
    public function __construct(
        string $exampleId,
        string $type,
        int|null $typeTargetId,
        string $group,
        bool $isActive,
        string $key,
        string $value,
        array $details,
    ) {
        $this->data = [
            'exampleId' => $exampleId,
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'isActive' => $isActive,
            'key' => $key,
            'value' => $value,
            'details' => $details,
        ];
    }

    public function entryId(): string
    {
        return $this->data['exampleId'];
    }

    public function type(): string
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

    public function isActive(): bool
    {
        return $this->data['isActive'];
    }

    public function key(): string
    {
        return $this->data['key'];
    }

    public function value(): string
    {
        return $this->data['value'];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->data['details'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
