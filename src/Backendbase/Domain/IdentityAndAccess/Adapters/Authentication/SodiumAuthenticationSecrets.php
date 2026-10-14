<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Infrastructure\Configuration\IdentitySettings;

use function array_pad;
use function explode;
use function hash_equals;
use function hash_hmac;
use function password_hash;
use function password_verify;

use const PASSWORD_ARGON2ID;

final readonly class SodiumAuthenticationSecrets implements AuthenticationSecrets
{
    public function __construct(private IdentitySettings $settings)
    {
    }

    public function hashPassword(Password $password): string
    {
        return password_hash($password->forHashing(), PASSWORD_ARGON2ID);
    }

    public function verifyPassword(Password $password, string $hash): bool
    {
        return password_verify($password->forHashing(), $hash);
    }

    public function proofHash(string $proof): string
    {
        $settings = $this->settings;
        $id       = $settings->encryptionKeyId();
        $keys     = $settings->encryptionKeys();

        return $id . ':' . hash_hmac('sha256', $proof, $keys[$id]);
    }

    public function verifyProof(string $proof, string $expectedHash): bool
    {
        [$id, $digest] = array_pad(explode(':', $expectedHash, 2), 2, '');
        $settings      = $this->settings;
        $keys          = $settings->encryptionKeys();

        return isset($keys[$id]) && hash_equals($digest, hash_hmac('sha256', $proof, $keys[$id]));
    }
}
