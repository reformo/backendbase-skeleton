<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;

final readonly class AccountProfile
{
    public function __construct(private AccountCredentials $credentials, private AccountPrivileges $privileges)
    {
    }

    public function revise(Email|null $email, PasswordHash|null $passwordHash, AccountPrivileges|null $privileges): self
    {
        return new self(
            $this->credentials->revise($email, $passwordHash),
            $privileges ?? $this->privileges,
        );
    }

    public function credentials(): AccountCredentials
    {
        return $this->credentials;
    }

    public function privileges(): AccountPrivileges
    {
        return $this->privileges;
    }
}
