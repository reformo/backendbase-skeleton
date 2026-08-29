<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;

final class Account
{
    private function __construct(private AccountId $id, private AccountProfile $profile)
    {
    }

    public static function register(
        AccountId $id,
        Email $email,
        PasswordHash $passwordHash,
        AccountPrivileges $privileges,
    ): self {
        return new self($id, new AccountProfile(new AccountCredentials($email, $passwordHash), $privileges));
    }

    public static function reconstitute(
        AccountId $id,
        Email $email,
        PasswordHash $passwordHash,
        AccountPrivileges $privileges,
    ): self {
        return self::register($id, $email, $passwordHash, $privileges);
    }

    public function revise(Email|null $email, PasswordHash|null $passwordHash, AccountPrivileges|null $privileges): void
    {
        $this->profile = $this->profile->revise($email, $passwordHash, $privileges);
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->profile->credentials()->email();
    }

    public function passwordHash(): PasswordHash
    {
        return $this->profile->credentials()->passwordHash();
    }

    public function privileges(): AccountPrivileges
    {
        return $this->profile->privileges();
    }
}
