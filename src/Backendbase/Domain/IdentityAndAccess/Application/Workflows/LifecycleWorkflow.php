<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;

final readonly class LifecycleWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationGate $gate)
    {
    }

    public function authorizeLogout(Principal $principal): void
    {
        $currentGate = $this->gate;

        $currentGate->confirm($principal);
    }

    public function closeAccount(Principal $principal, DateTimeImmutable $now): void
    {
        $currentStore = $this->store;

        $currentStore->transaction(function () use ($principal, $now): void {
            $currentStore = $this->store;
            $currentGate  = $this->gate;

            $identity = $principal->identity();
            $account  = $currentStore->accountForUpdate($identity['accountId']);
            $currentGate->assertCurrent($principal);
            $account->close($now);
            $currentStore->saveAccount($account);
        });
    }
}
