<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use DateTimeImmutable;

final readonly class AuthenticationTools
{
    public function __construct(private Credentials $credentials, private AuthenticationClock $clock)
    {
    }

    public function hashPassword(Password $password): string
    {
        $currentCredentials = $this->credentials;

        return $currentCredentials->hashPassword($password);
    }

    public function initializeCredentials(string $accountId, string $hash): void
    {
        $currentCredentials = $this->credentials;

        $currentCredentials->initialize($accountId, $hash);
    }

    public function assertPassword(string $accountId, Password $password): void
    {
        $currentCredentials = $this->credentials;

        $currentCredentials->assertPassword($accountId, $password);
    }

    public function sessionExpiresAt(): DateTimeImmutable
    {
        $currentClock = $this->clock;

        return $currentClock->sessionExpiresAt();
    }
}
