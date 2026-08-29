<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface AccountAuthenticationRepository
{
    public function findByEmail(string $email): AccountAuthentication|null;
}
