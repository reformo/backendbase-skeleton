<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Query;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<IdentityOperationResult> */
final readonly class GetIdentityOperation implements Query
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
    public function jsonSerialize(): array
    {
        return ['operationId' => $this->operationId()];
    }
}
