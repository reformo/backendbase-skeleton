<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;

interface TokenRegistry
{
    public function activate(Principal $principal, DateTimeImmutable $expiresAt): void;

    public function assertActive(Principal $principal): void;

    public function revokeAccount(string $accountId): void;

    public function revokeToken(Principal $principal): void;

    public function revokeSession(Principal $principal): void;
}
