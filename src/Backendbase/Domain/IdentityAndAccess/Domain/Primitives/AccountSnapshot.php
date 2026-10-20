<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Profiles\AccountNames;

use function array_key_exists;

final readonly class AccountSnapshot
{
    public function __construct(
        public string $email,
        public string $mobile,
        public string|null $firstName,
        public string|null $lastName,
        public AccountState $authentication,
        public string|null $deletedAt,
        public int $revision,
        public string $createdAt,
    ) {
        new AccountEmail($email);
        new AccountMobile($mobile);
        new AccountNames($firstName, $lastName);
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['email', 'mobile', 'firstName', 'lastName', 'ownershipVerified', 'enabled', 'authVersion', 'registrationAccepted', 'deletedAt', 'revision', 'createdAt']);
        $authentication = AccountState::fromArray([
            'ownershipVerified' => StateField::boolean($values, 'ownershipVerified'),
            'enabled' => StateField::boolean($values, 'enabled'),
            'authVersion' => StateField::integer($values, 'authVersion'),
            'registrationAccepted' => StateField::boolean($values, array_key_exists('registrationAccepted', $values) ? 'registrationAccepted' : 'ownershipVerified'),
        ]);
        $names          = ['firstName' => null, 'lastName' => null, ...$values];

        return new self(
            StateField::string($values, 'email'),
            StateField::string($values, 'mobile'),
            StateField::nullableString($names, 'firstName'),
            StateField::nullableString($names, 'lastName'),
            $authentication,
            StateField::nullableString($values, 'deletedAt'),
            StateField::integer($values, 'revision'),
            StateField::string($values, 'createdAt'),
        );
    }

    public function withAuthentication(AccountState $authentication, string|null $deletedAt): self
    {
        $email     = $this->email;
        $mobile    = $this->mobile;
        $firstName = $this->firstName;
        $lastName  = $this->lastName;
        $revision  = $this->revision;
        $createdAt = $this->createdAt;

        return new self($email, $mobile, $firstName, $lastName, $authentication, $deletedAt, $revision, $createdAt);
    }

    /** @return array{firstName: string|null, lastName: string|null, email: string, mobile: string, ownershipVerified: bool, registrationAccepted: bool, enabled: bool, authVersion: int, deletedAt: string|null, revision: int, createdAt: string} */
    public function toArray(): array
    {
        $authentication = $this->authentication;
        $firstName      = $this->firstName;
        $lastName       = $this->lastName;
        $email          = $this->email;
        $mobile         = $this->mobile;
        $deletedAt      = $this->deletedAt;
        $revision       = $this->revision;
        $createdAt      = $this->createdAt;

        return ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'mobile' => $mobile, ...$authentication->toArray(), 'deletedAt' => $deletedAt, 'revision' => $revision, 'createdAt' => $createdAt];
    }
}
