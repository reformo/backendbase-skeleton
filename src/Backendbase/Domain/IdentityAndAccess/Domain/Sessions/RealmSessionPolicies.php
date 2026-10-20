<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class RealmSessionPolicies
{
    public function __construct(private SessionPolicy $user, private SessionPolicy $admin)
    {
    }

    public function forRealm(Realm $realm): SessionPolicy
    {
        if ($realm === Realm::USER) {
            return $this->user;
        }

        return $this->admin;
    }
}
