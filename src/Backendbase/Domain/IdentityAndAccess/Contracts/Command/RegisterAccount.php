<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command;

use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\RegisterAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use Override;

#[CQRSHandler(RegisterAccountHandler::class)]
final readonly class RegisterAccount implements Command
{
    public function __construct(
        private AccountId $accountId,
        private Email $email,
        private PasswordHash $passwordHash,
        private AccountPrivileges $privileges,
        private AccessControl $accessControl,
    ) {
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    public function privileges(): AccountPrivileges
    {
        return $this->privileges;
    }

    public function accessControl(): AccessControl
    {
        return $this->accessControl;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array{accountId: string, email: string, privilegeSlugs: list<string>} */
    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId->toString(),
            'email' => $this->email->toString(),
            'privilegeSlugs' => $this->privileges->slugs(),
        ];
    }
}
