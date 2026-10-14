<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface CredentialStore
{
    /** @return array{passwordHash: string} */
    public function read(string $accountId): array;

    /** @param array{passwordHash: string} $credentials */
    public function save(string $accountId, array $credentials): void;
}
