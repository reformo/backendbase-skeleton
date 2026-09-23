<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS\Fixtures;

use Backendbase\Shared\CQRS\Command;
use Override;

final readonly class RegistryCommand implements Command
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
