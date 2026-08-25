<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;

final readonly class NewExampleAddedMessage implements EventMessage
{
    /** @param array<string, mixed> $details */
    public function __construct(
        private string $exampleId,
        private string $type,
        private int|null $typeTargetId,
        private string $group,
        private bool $isActive,
        private string $key,
        private string $value,
        private array $details,
    ) {
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function typeTargetId(): int|null
    {
        return $this->typeTargetId;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exampleId' => $this->exampleId,
            'type' => $this->type,
            'typeTargetId' => $this->typeTargetId,
            'group' => $this->group,
            'isActive' => $this->isActive,
            'key' => $this->key,
            'value' => $this->value,
            'details' => $this->details,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
