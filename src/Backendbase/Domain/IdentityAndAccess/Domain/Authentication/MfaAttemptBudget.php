<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Authentication;

use DateTimeImmutable;
use DateTimeZone;

final class MfaAttemptBudget
{
    public function __construct(private int $failedAttempts, private DateTimeImmutable|null $blockedUntil)
    {
        if ($failedAttempts < 0 || $failedAttempts > 5) {
            throw AuthenticationDenied::create('The authentication attempt state is invalid.');
        }
    }

    public static function reconstitute(int $failedAttempts, string|null $blockedUntil): self
    {
        if ($blockedUntil === null) {
            return new self($failedAttempts, null);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $blockedUntil, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s.u') !== $blockedUntil) {
            throw AuthenticationDenied::create('The authentication attempt state is invalid.');
        }

        return new self($failedAttempts, $date);
    }

    public function permitsAttempt(DateTimeImmutable $now): bool
    {
        if ($this->blockedUntil !== null && $this->blockedUntil > $now) {
            return false;
        }

        if ($this->blockedUntil !== null) {
            $this->accepted();
        }

        return true;
    }

    public function failed(DateTimeImmutable $now): void
    {
        if (! $this->permitsAttempt($now)) {
            return;
        }

        $this->failedAttempts++;
        if ($this->failedAttempts < 5) {
            return;
        }

        $this->blockedUntil = $now->modify('+300 seconds');
    }

    public function accepted(): void
    {
        $this->failedAttempts = 0;
        $this->blockedUntil   = null;
    }

    /** @return array{failedMfaAttempts: int, blockedUntil: string|null} */
    public function snapshot(): array
    {
        $currentBlockedUntil = $this->blockedUntil;

        return ['failedMfaAttempts' => $this->failedAttempts, 'blockedUntil' => $currentBlockedUntil?->format('Y-m-d H:i:s.u')];
    }
}
