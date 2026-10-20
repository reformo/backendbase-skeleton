<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;

final readonly class SessionRenewal
{
    public function __construct(private Realm $realm, private RefreshToken $token)
    {
    }

    public function realm(): Realm
    {
        return $this->realm;
    }

    public function token(): RefreshToken
    {
        return $this->token;
    }
}
