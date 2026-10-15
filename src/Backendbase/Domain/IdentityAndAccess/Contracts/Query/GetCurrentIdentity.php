<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Query;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<ActorHistory> */
final readonly class GetCurrentIdentity implements Query
{
    public function __construct(private Principal $principal)
    {
    }

    public function principal(): Principal
    {
        return $this->principal;
    }

    /** @return array{} */
    public function jsonSerialize(): array
    {
        return [];
    }
}
