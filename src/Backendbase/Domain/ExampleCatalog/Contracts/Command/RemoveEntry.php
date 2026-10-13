<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Command;

use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Shared\CQRS\Command;
use Override;

readonly class RemoveEntry implements Command
{
    public function __construct(
        private EntryIdentity $identity,
    ) {
    }

    public function identity(): EntryIdentity
    {
        return $this->identity;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identity' => $this->identity->toArray()];
    }
}
