<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;

interface AccountAuthorizationState
{
    public function revokeAll(AccountId $accountId): void;
}
