<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

enum Realm: string
{
    case USER  = 'USER';
    case ADMIN = 'ADMIN';

    public function actorType(): string
    {
        return $this === self::USER ? 'User' : 'SystemAdmin';
    }
}
