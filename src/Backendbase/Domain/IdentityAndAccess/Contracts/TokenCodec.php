<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

interface TokenCodec
{
    public function encode(Principal $principal, DateTimeImmutable $expiresAt): string;

    public function decode(Realm $expectedRealm, string $token): Principal;
}
