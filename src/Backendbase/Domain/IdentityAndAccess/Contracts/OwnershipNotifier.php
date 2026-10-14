<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;

interface OwnershipNotifier
{
    public function send(string $challengeId, AccountEmail $email, string $code): void;
}
