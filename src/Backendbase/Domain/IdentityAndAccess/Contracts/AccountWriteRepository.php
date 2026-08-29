<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;

interface AccountWriteRepository
{
    public function register(Account $account): void;

    public function getActive(AccountId $accountId): Account;

    public function save(Account $account): void;
}
