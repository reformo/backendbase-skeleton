<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Profiles;

final readonly class AccountNames
{
    private AccountName|null $firstName;
    private AccountName|null $lastName;

    public function __construct(string|null $firstName = null, string|null $lastName = null)
    {
        $this->firstName = $firstName === null ? null : new AccountName($firstName);
        $this->lastName  = $lastName === null ? null : new AccountName($lastName);
    }

    /** @return array{firstName: string|null, lastName: string|null} */
    public function snapshot(): array
    {
        $firstName = $this->firstName;
        $lastName  = $this->lastName;

        return ['firstName' => $firstName?->toString(), 'lastName' => $lastName?->toString()];
    }
}
