<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\SessionLifetime;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;

final readonly class AuthenticationClock
{
    public function __construct(private Clock $clock, private SessionLifetime $lifetime)
    {
    }

    public function now(): DateTimeImmutable
    {
        $currentClock = $this->clock;

        return $currentClock->now();
    }

    public function sessionExpiresAt(): DateTimeImmutable
    {
        $currentLifetime = $this->lifetime;

        $now = $this->now();

        return $currentLifetime->expiresAt($now);
    }
}
