<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\ExternalIntegrationEvents\V1;

use Backendbase\Shared\Domain\Messaging\EventMessage;
use UnexpectedValueException;

final readonly class NewExampleAddedMessage implements EventMessage
{
    public function __construct(
        private string $exampleId,
        private NewExampleAddedCommand $command,
    ) {
        if ($exampleId !== $command->exampleId()) {
            throw new UnexpectedValueException('The external example identifiers must match.');
        }
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }

    public function type(): string
    {
        return $this->command->type();
    }

    public function typeTargetId(): int|null
    {
        return $this->command->typeTargetId();
    }

    public function group(): string
    {
        return $this->command->group();
    }

    public function isActive(): bool
    {
        return $this->command->isActive();
    }

    public function key(): string
    {
        return $this->command->key();
    }

    public function value(): string
    {
        return $this->command->value();
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->command->details();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exampleId' => $this->exampleId,
            'command' => $this->command->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
