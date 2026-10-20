<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use DateTimeImmutable;

interface TokenRegistry
{
    public function activate(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session = null): void;

    public function assertActive(Principal $principal, bool $touch = false): void;

    public function session(Realm $realm, RefreshToken $token, bool $allowUsed = false): SessionState;

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): void;

    public function revokeAccount(string $accountId): void;

    public function revokeToken(Principal $principal): void;

    public function revokeSession(Principal $principal): void;
}
