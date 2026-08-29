<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

final readonly class AccountAuthentication
{
    /** @param list<string> $privileges */
    public function __construct(
        private string $uuid,
        private string $email,
        private string $passwordHash,
        private array $privileges,
    ) {
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function privileges(): array
    {
        return $this->privileges;
    }
}
