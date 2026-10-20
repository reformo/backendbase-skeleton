<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use DateTimeImmutable;

final readonly class AuthenticationTokens
{
    public function __construct(private TokenCodec $codec, private TokenRegistry $registry)
    {
    }

    public function issue(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session = null): string
    {
        $currentCodec    = $this->codec;
        $currentRegistry = $this->registry;

        $token = $currentCodec->encode($principal, $expiresAt);
        $currentRegistry->activate($principal, $expiresAt, $session);

        return $token;
    }

    public function verify(Realm $expectedRealm, string $token): Principal
    {
        $currentCodec    = $this->codec;
        $currentRegistry = $this->registry;

        $principal = $currentCodec->decode($expectedRealm, $token);
        $currentRegistry->assertActive($principal);

        return $principal;
    }

    public function revokeSession(Principal $principal): void
    {
        $currentRegistry = $this->registry;

        $currentRegistry->revokeSession($principal);
    }

    public function touch(Principal $principal): void
    {
        $registry = $this->registry;
        $registry->assertActive($principal, true);
    }

    public function session(Realm $realm, RefreshToken $token, bool $allowUsed = false): SessionState
    {
        $registry = $this->registry;

        return $registry->session($realm, $token, $allowUsed);
    }

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt): string
    {
        $codec     = $this->codec;
        $registry  = $this->registry;
        $principal = $replacement->principal;
        $token     = $codec->encode($principal, $expiresAt);
        $registry->rotate($provided, $replacement, $expiresAt);

        return $token;
    }

    public function revokeAccount(string $accountId): void
    {
        $currentRegistry = $this->registry;

        $currentRegistry->revokeAccount($accountId);
    }

    public function revokeToken(Principal $principal): void
    {
        $currentRegistry = $this->registry;

        $currentRegistry->revokeToken($principal);
    }
}
