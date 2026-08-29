<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use DateTimeImmutable;

/**
 * @phpstan-type AccountState array{
 *     id: AccountId,
 *     email: Email,
 *     passwordHash: PasswordHash,
 *     privileges: AccountPrivileges,
 *     retiredAt: DateTimeImmutable|null
 * }
 */

final class Account
{
    /** @param AccountState $state */
    private function __construct(private array $state)
    {
    }

    public static function register(
        AccountId $id,
        Email $email,
        PasswordHash $passwordHash,
        AccountPrivileges $privileges,
    ): self {
        return new self([
            'id' => $id,
            'email' => $email,
            'passwordHash' => $passwordHash,
            'privileges' => $privileges,
            'retiredAt' => null,
        ]);
    }

    public static function reconstitute(
        AccountId $id,
        Email $email,
        PasswordHash $passwordHash,
        AccountPrivileges $privileges,
        DateTimeImmutable|null $retiredAt = null,
    ): self {
        return new self([
            'id' => $id,
            'email' => $email,
            'passwordHash' => $passwordHash,
            'privileges' => $privileges,
            'retiredAt' => $retiredAt,
        ]);
    }

    public function revise(Email|null $email, PasswordHash|null $passwordHash, AccountPrivileges|null $privileges): void
    {
        $this->state['email']        = $email ?? $this->state['email'];
        $this->state['passwordHash'] = $passwordHash ?? $this->state['passwordHash'];
        $this->state['privileges']   = $privileges ?? $this->state['privileges'];
    }

    public function retire(): void
    {
        $this->state['retiredAt'] = DateTimeImmutableFactory::create();
    }

    public function isRetired(): bool
    {
        return $this->state['retiredAt'] !== null;
    }

    public function retiredAt(): DateTimeImmutable|null
    {
        return $this->state['retiredAt'];
    }

    public function id(): AccountId
    {
        return $this->state['id'];
    }

    public function email(): Email
    {
        return $this->state['email'];
    }

    public function passwordHash(): PasswordHash
    {
        return $this->state['passwordHash'];
    }

    public function privileges(): AccountPrivileges
    {
        return $this->state['privileges'];
    }
}
