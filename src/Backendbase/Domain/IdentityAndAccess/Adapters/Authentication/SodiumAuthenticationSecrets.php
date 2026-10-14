<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use DateTimeImmutable;

use function array_pad;
use function base64_decode;
use function base64_encode;
use function explode;
use function hash_equals;
use function hash_hmac;
use function password_hash;
use function password_verify;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function strlen;
use function substr;

use const PASSWORD_ARGON2ID;
use const SODIUM_CRYPTO_SECRETBOX_MACBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

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

    public function newTotpSecret(): string
    {
        return Totp::secret();
    }

    public function encrypt(string $secret): string
    {
        $settings  = $this->settings;
        $id        = $settings->encryptionKeyId();
        $keys      = $settings->encryptionKeys();
        $nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($secret, $nonce, $keys[$id]);

        return $id . ':' . base64_encode($nonce . $encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        [$id, $encoded] = array_pad(explode(':', $ciphertext, 2), 2, '');
        $settings       = $this->settings;
        $keys           = $settings->encryptionKeys();
        $bytes          = base64_decode($encoded, true);
        if (! isset($keys[$id]) || $bytes === false || strlen($bytes) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw AuthenticationDenied::create('The authentication factor is invalid.');
        }

        $nonce  = substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $secret = sodium_crypto_secretbox_open(substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $keys[$id]);
        if ($secret === false) {
            throw AuthenticationDenied::create('The authentication factor is invalid.');
        }

        return $secret;
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

    public function totpStep(string $secret, string $code, int $lastStep, DateTimeImmutable $now): int|null
    {
        return Totp::step($secret, $code, $lastStep, $now);
    }
}
