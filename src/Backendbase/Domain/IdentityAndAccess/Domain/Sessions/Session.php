<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use DateTimeImmutable;

final class Session
{
    public function __construct(private SessionState $state)
    {
    }

    public static function start(Principal $principal, SessionPolicy $policy, DateTimeImmutable $now, RefreshToken $refreshToken): self
    {
        $createdAt        = $now->getTimestamp();
        $maximumExpiresAt = $policy->maximumExpiresAt($createdAt);
        $refreshHash      = $refreshToken->digest();

        return new self(new SessionState($principal, $policy, $createdAt, $createdAt, $maximumExpiresAt, $refreshHash));
    }

    public function assertActive(DateTimeImmutable $now): void
    {
        $state         = $this->state;
        $timestamp     = $now->getTimestamp();
        $createdAt     = $state->createdAt;
        $idleExpiresAt = $this->idleExpiresAt();
        $idleTimestamp = $idleExpiresAt->getTimestamp();
        if ($timestamp < $createdAt || $timestamp >= $idleTimestamp) {
            throw AuthenticationDenied::create('The session has expired.');
        }
    }

    public function rotate(RefreshToken $replacement, DateTimeImmutable $now): void
    {
        $this->assertActive($now);
        $state            = $this->state;
        $principal        = $state->principal;
        $principal        = $principal->renewToken();
        $policy           = $state->policy;
        $createdAt        = $state->createdAt;
        $lastUsedAt       = $now->getTimestamp();
        $maximumExpiresAt = $state->maximumExpiresAt;
        $refreshHash      = $replacement->digest();
        $this->state      = new SessionState($principal, $policy, $createdAt, $lastUsedAt, $maximumExpiresAt, $refreshHash);
    }

    public function tokenExpiresAt(DateTimeImmutable $now): DateTimeImmutable
    {
        $this->assertActive($now);
        $state  = $this->state;
        $policy = $state->policy;

        $maximumExpiresAt = $state->maximumExpiresAt;

        return $policy->tokenExpiresAt($now, $maximumExpiresAt);
    }

    public function idleExpiresAt(): DateTimeImmutable
    {
        $state  = $this->state;
        $policy = $state->policy;

        $lastUsedAt       = $state->lastUsedAt;
        $maximumExpiresAt = $state->maximumExpiresAt;

        return new DateTimeImmutable('@' . $policy->idleExpiresAt($lastUsedAt, $maximumExpiresAt));
    }

    public function snapshot(): SessionState
    {
        return $this->state;
    }
}
