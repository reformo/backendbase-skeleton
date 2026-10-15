<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;

final readonly class Credentials
{
    public function __construct(private CredentialStore $store, private AuthenticationSecrets $secrets)
    {
    }

    public function hashPassword(Password $password): string
    {
        $currentSecrets = $this->secrets;

        return $currentSecrets->hashPassword($password);
    }

    public function initialize(string $accountId, string $passwordHash): void
    {
        $currentStore = $this->store;

        $currentStore->save($accountId, ['passwordHash' => $passwordHash]);
    }

    public function assertPassword(string $accountId, Password $password): void
    {
        $currentStore   = $this->store;
        $currentSecrets = $this->secrets;

        $credentials = $currentStore->read($accountId);
        if (! $currentSecrets->verifyPassword($password, $credentials['passwordHash'])) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }
}
