<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final readonly class AuthenticationTokens
{
    public function __construct(private TokenCodec $codec, private TokenRegistry $registry)
    {
    }

    public function issue(Principal $principal, DateTimeImmutable $expiresAt): string
    {
        $currentCodec    = $this->codec;
        $currentRegistry = $this->registry;

        $token = $currentCodec->encode($principal, $expiresAt);
        $currentRegistry->activate($principal, $expiresAt);

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
