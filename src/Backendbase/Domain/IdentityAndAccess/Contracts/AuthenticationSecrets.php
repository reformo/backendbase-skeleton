<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;

interface AuthenticationSecrets
{
    public function hashPassword(Password $password): string;

    public function verifyPassword(Password $password, string $hash): bool;

    public function proofHash(string $proof): string;

    public function verifyProof(string $proof, string $expectedHash): bool;
}
