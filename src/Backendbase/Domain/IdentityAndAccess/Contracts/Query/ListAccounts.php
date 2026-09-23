<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Query;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Query;
use Override;

/** @implements Query<list<AccountListItem>> */
final readonly class ListAccounts implements Query
{
    public function __construct(private AccessControl $accessControl)
    {
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

    /** @return array{} */
    public function toArray(): array
    {
        return [];
    }
}
