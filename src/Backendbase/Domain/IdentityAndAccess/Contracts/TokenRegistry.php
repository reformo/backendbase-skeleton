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

    /**
     * Allow a recognized consumed credential for replay authentication and result checks.
     * A new rotation must require the current credential. A replay result must still be current.
     */
    public function session(Realm $realm, RefreshToken $token, bool $allowUsed = false): SessionState;

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): void;

    public function revokeAccount(string $accountId): void;

    /** Revoke this access token without ending its renewable session. */
    public function revokeToken(Principal $principal): void;

    /** End the session and revoke the supplied access token. */
    public function revokeSession(Principal $principal): void;
}
