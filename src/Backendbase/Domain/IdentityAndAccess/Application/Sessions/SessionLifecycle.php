<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;

final readonly class SessionLifecycle
{
    public function __construct(private AuthenticationClock $clock, private SessionIssuance $issuance)
    {
    }

    /** @return array<string, string> */
    public function start(Principal $principal): array
    {
        $clock        = $this->clock;
        $issuance     = $this->issuance;
        $realm        = $principal->realm();
        $policy       = $clock->policy($realm);
        $now          = $clock->now();
        $refreshToken = RefreshToken::create($principal);
        $session      = Session::start($principal, $policy, $now, $refreshToken);

        return $issuance->start($session, $refreshToken, $now);
    }

    /** @return array<string, string> */
    public function renew(Realm $realm, RefreshToken $token): array
    {
        $clock    = $this->clock;
        $issuance = $this->issuance;

        $now = $clock->now();

        return $issuance->renew($realm, $token, $now);
    }
}
