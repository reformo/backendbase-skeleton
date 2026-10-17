<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface CredentialStore
{
    public function read(string $accountId): string;

    public function save(string $accountId, string $passwordHash): void;
}
