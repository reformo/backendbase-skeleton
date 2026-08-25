<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Command;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\RemoveExampleHandler;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Override;

use function get_object_vars;

#[CQRSHandler(RemoveExampleHandler::class)]
readonly class RemoveExample implements Command
{
    public function __construct(private string $exampleId)
    {
    }

    public function exampleId(): string
    {
        return $this->exampleId;
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
