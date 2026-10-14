<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\SodiumAuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use PHPUnit\Framework\TestCase;

final class AuthenticationSecretsTest extends TestCase
{
    public function testPasswordsAreHashedAndOwnershipProofsAreAuthenticated(): void
    {
        $secrets  = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        $password = new Password('valid-password-123');
        $hash     = $secrets->hashPassword($password);
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($secrets->verifyPassword($password, $hash));
        self::assertFalse($secrets->verifyPassword(new Password('different-password'), $hash));
        $proof = $secrets->proofHash('verification-code');
        self::assertTrue($secrets->verifyProof('verification-code', $proof));
        self::assertFalse($secrets->verifyProof('wrong-code', $proof));
        self::assertFalse($secrets->verifyProof('verification-code', 'unknown:hash'));
    }
}
