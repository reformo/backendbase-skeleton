<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\CQRS\Command;

abstract readonly class IdentityCommand implements Command
{
    private IdentityId $id;

    public function __construct(string $operationId)
    {
        $this->id = IdentityId::fromString($operationId);
    }

    public function operationId(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    /** @return array{operationId: string} */
    public function toArray(): array
    {
        return ['operationId' => $this->operationId()];
    }

    /** @return array{operationId: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
