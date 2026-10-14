<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use DateTimeImmutable;

interface AuthenticationSecrets
{
    public function hashPassword(Password $password): string;

    public function verifyPassword(Password $password, string $hash): bool;

    public function newTotpSecret(): string;

    public function encrypt(string $secret): string;

    public function decrypt(string $ciphertext): string;

    public function proofHash(string $proof): string;

    public function verifyProof(string $proof, string $expectedHash): bool;

    public function totpStep(string $secret, string $code, int $lastStep, DateTimeImmutable $now): int|null;
}
