<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Command;
use Override;

final readonly class RetireAccount implements Command
{
    public function __construct(private AccountId $accountId, private AccessControl $accessControl)
    {
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
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

    /** @return array{accountId: string} */
    public function toArray(): array
    {
        return ['accountId' => $this->accountId->toString()];
    }
}
