<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;

final readonly class AccountCredentials
{
    public function __construct(private Email $email, private PasswordHash $passwordHash)
    {
    }

    public function revise(Email|null $email, PasswordHash|null $passwordHash): self
    {
        return new self($email ?? $this->email, $passwordHash ?? $this->passwordHash);
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }
}
