<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Command;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\AddNewExampleHandler;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Override;

#[CQRSHandler(AddNewExampleHandler::class)]
class AddNewExample implements Command
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        private string $exampleId,
        private ExampleType $type,
        private int|null $typeTargetId,
        private string $group,
        private bool $isActive,
        private string $key,
        private string $value,
        private AccessControl $accessControl,
        private array|null $details = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details ?? [];
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }

    public function type(): ExampleType
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

    public function accessControl(): AccessControl
    {
        return $this->accessControl;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exampleId' => $this->exampleId,
            'type' => $this->type->value,
            'typeTargetId' => $this->typeTargetId,
            'group' => $this->group,
            'isActive' => $this->isActive,
            'key' => $this->key,
            'value' => $this->value,
            'details' => $this->details,
        ];
    }
}
