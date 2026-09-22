<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;

interface AccountWriteRepository
{
    /** @param callable(): void $change */
    public function withAccountLock(AccountId $accountId, callable $change): void;

    public function register(Account $account): void;

    public function getActive(AccountId $accountId): Account;

    public function save(Account $account): void;
}
