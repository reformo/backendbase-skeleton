<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Command;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\ChangeExampleHandler;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Override;

use function get_object_vars;

#[CQRSHandler(ChangeExampleHandler::class)]
class ChangeExample implements Command
{
    private bool|null $isActive = null;
    private string|null $value  = null;
    /** @var array<string, mixed>|null */
    private array|null $details = null;

    public function __construct(private string $exampleId)
    {
    }

    public function exampleId(): string
    {
        return $this->exampleId;
    }

    public function isActive(): bool|null
    {
        return $this->isActive;
    }

    public function setIsActive(bool|null $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function setValue(string|null $value): self
    {
        $this->value = $value;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function details(): array|null
    {
        return $this->details;
    }

    /** @param array<string, mixed>|null $details */
    public function setDetails(array|null $details): self
    {
        $this->details = $details;

        return $this;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
