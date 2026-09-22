<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface AccountAuthenticationRepository
{
    /** @param callable(): string $authenticate */
    public function withAuthenticationLock(string $email, callable $authenticate): string;

    public function findByEmail(string $email): AccountAuthentication|null;
}
