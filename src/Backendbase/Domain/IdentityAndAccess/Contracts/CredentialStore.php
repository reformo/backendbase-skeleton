<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface CredentialStore
{
    /** @return array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} */
    public function read(string $accountId): array;

    /** @param array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} $credentials */
    public function save(string $accountId, array $credentials): void;
}
