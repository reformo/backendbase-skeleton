<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\SodiumAuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Totp;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;

final class AuthenticationSecretsTest extends TestCase
{
    public function testPasswordsAreHashedAndFactorsAreAuthenticatedAndEncrypted(): void
    {
        $secrets  = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        $password = new Password('valid-password-123');
        $hash     = $secrets->hashPassword($password);
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($secrets->verifyPassword($password, $hash));
        self::assertFalse($secrets->verifyPassword(new Password('different-password'), $hash));
        $factor = $secrets->newTotpSecret();
        self::assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $factor);
        $ciphertext = $secrets->encrypt($factor);
        self::assertStringNotContainsString($factor, $ciphertext);
        self::assertSame($factor, $secrets->decrypt($ciphertext));
        $proof = $secrets->proofHash('verification-code');
        self::assertTrue($secrets->verifyProof('verification-code', $proof));
        self::assertFalse($secrets->verifyProof('wrong-code', $proof));
        self::assertFalse($secrets->verifyProof('verification-code', 'unknown:hash'));
    }

    #[DataProvider('invalidCiphertexts')]
    public function testCiphertextRejectsCorruptionAndUnknownKeys(string $ciphertext): void
    {
        $secrets = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        $this->expectException(AuthenticationDenied::class);
        $secrets->decrypt($ciphertext);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCiphertexts(): iterable
    {
        yield 'missing separator' => ['invalid'];
        yield 'invalid base64' => ['factor-key:!'];
        yield 'short payload' => ['factor-key:YQ=='];
        yield 'unknown key' => ['unknown:' . base64_encode(str_repeat('a', 60))];
        yield 'invalid authenticator' => ['factor-key:' . base64_encode(str_repeat('a', 60))];
    }

    public function testTotpMatchesRfcVectorAndRejectsStepReuse(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $now    = new DateTimeImmutable('@59');
        self::assertSame('287082', Totp::code($secret, 1));
        $secrets = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        self::assertSame(1, $secrets->totpStep($secret, '287082', -1, $now));
        self::assertNull($secrets->totpStep($secret, '287082', 1, $now));
        self::assertNull(Totp::step($secret, '123456', -1, $now));
        self::assertNull(Totp::step($secret, '123', -1, $now));
        $this->expectException(AuthenticationDenied::class);
        Totp::code('invalid-secret', 1);
    }
}
