<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RealmSessionPolicies;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final readonly class AuthenticationClock
{
    public function __construct(private Clock $clock, private RealmSessionPolicies $policies)
    {
    }

    public function now(): DateTimeImmutable
    {
        $currentClock = $this->clock;

        return $currentClock->now();
    }

    public function policy(Realm $realm): SessionPolicy
    {
        $policies = $this->policies;

        return $policies->forRealm($realm);
    }

    public function tokenExpiresAt(Realm $realm): DateTimeImmutable
    {
        $policy    = $this->policy($realm);
        $now       = $this->now();
        $timestamp = $now->getTimestamp();
        $maximum   = $policy->maximumExpiresAt($timestamp);

        return $policy->tokenExpiresAt($now, $maximum);
    }
}
