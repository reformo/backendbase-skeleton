<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;
use Throwable;

final readonly class SessionIssuance
{
    public function __construct(private AuthenticationTokens $tokens, private AuthenticationGate $gate)
    {
    }

    public function issue(Principal $principal, DateTimeImmutable $expiresAt): string
    {
        $currentGate   = $this->gate;
        $currentTokens = $this->tokens;

        $currentGate->confirm($principal);
        $token = $currentTokens->issue($principal, $expiresAt);
        try {
            $currentGate->confirm($principal);
        } catch (Throwable $exception) {
            $currentTokens->revokeSession($principal);

            throw $exception;
        }

        return $token;
    }
}
