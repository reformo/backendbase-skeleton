<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use DateTimeImmutable;

/** @phpstan-type Token array{Principal, DateTimeImmutable} */
final class MemorySessionRecord
{
    /** @var array{principal: Principal, expiresAt: DateTimeImmutable, session: SessionState|null, tokens: array<string, Token>, used: array<string, true>} */
    private array $values;

    public function __construct(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session)
    {
        $identity     = $principal->identity();
        $this->values = ['principal' => $principal, 'expiresAt' => $expiresAt, 'session' => $session, 'tokens' => [$identity['jti'] => [$principal, $expiresAt]], 'used' => []];
    }

    public function activeAt(DateTimeImmutable $now): bool
    {
        try {
            $this->assertLive($now);

            return true;
        } catch (AuthenticationDenied) {
            return false;
        }
    }

    public function assertActive(Principal $principal, DateTimeImmutable $now, bool $touch): void
    {
        $this->assertLive($now);
        $identity = $principal->identity();
        $token    = $this->values['tokens'][$identity['jti']] ?? null;
        if ($token === null || $token[1] <= $now || $token[0]->claims() !== $principal->claims()) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $state = $this->values['session'];
        if (! $touch || $state === null) {
            return;
        }

        $this->values['session'] = new SessionState($state->principal, $state->policy, $state->createdAt, $now->getTimestamp(), $state->maximumExpiresAt, $state->refreshHash);
    }

    public function session(RefreshToken $token, DateTimeImmutable $now, bool $allowUsed): SessionState
    {
        $this->assertLive($now);
        $state = $this->values['session'];
        if ($state === null) {
            throw AuthenticationDenied::create('The session cannot be renewed.');
        }

        $hash = $token->digest();
        if ($state->refreshHash === $hash) {
            return $state;
        }

        if (isset($this->values['used'][$hash])) {
            return $this->used($state, $allowUsed);
        }

        throw AuthenticationDenied::create('The session cannot be renewed.');
    }

    public function rotate(RefreshToken $provided, SessionState $replacement, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->session($provided, $now, false);
        $principal                                 = $replacement->principal;
        $identity                                  = $principal->identity();
        $this->values['used'][$provided->digest()] = true;
        $this->values['session']                   = $replacement;
        $this->values['tokens'][$identity['jti']]  = [$principal, $expiresAt];
    }

    public function revokeToken(Principal $principal): void
    {
        $identity = $principal->identity();
        unset($this->values['tokens'][$identity['jti']]);
    }

    public function belongsTo(string $accountId): bool
    {
        $principal = $this->values['principal'];
        $identity  = $principal->identity();

        return $identity['accountId'] === $accountId;
    }

    private function assertLive(DateTimeImmutable $now): void
    {
        $state = $this->values['session'];
        if ($state !== null) {
            new Session($state)->assertActive($now);

            return;
        }

        if ($this->values['expiresAt'] <= $now || $this->values['tokens'] === []) {
            throw AuthenticationDenied::create('The session has expired.');
        }
    }

    private function used(SessionState $state, bool $allowUsed): SessionState
    {
        if ($allowUsed) {
            return $state;
        }

        $this->values['session'] = null;
        $this->values['tokens']  = [];

        throw AuthenticationDenied::create('The refresh token was already used.');
    }
}
