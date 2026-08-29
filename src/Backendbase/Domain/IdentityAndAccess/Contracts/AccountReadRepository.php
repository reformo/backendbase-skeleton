<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;

interface AccountReadRepository
{
    /** @return list<AccountListItem> */
    public function listActive(): array;
}
