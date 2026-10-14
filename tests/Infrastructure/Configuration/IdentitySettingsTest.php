<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\SodiumAuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;

final class IdentitySettingsTest extends TestCase
{
    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidRealmSettings')]
    public function testBothRealmPoliciesAreValidatedBeforeIssuance(array $changes): void
    {
        $settings = IdentityFixture::settings($changes);
        $this->expectException(AuthenticationUnavailable::class);
        $settings->realm(Realm::USER);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRealmSettings(): iterable
    {
        yield 'shared issuer' => [['ADMIN' => ['issuer' => 'https://user.example.com']]];
        yield 'shared audience' => [['ADMIN' => ['audience' => 'user-api']]];
        yield 'shared key material' => [['ADMIN' => ['key' => base64_encode(str_repeat('u', 32))]]];
        yield 'missing field' => [['USER' => ['issuer' => '']]];
        yield 'control character' => [['USER' => ['issuer' => "issuer\n"]]];
        yield 'invalid base64' => [['USER' => ['key' => '!']]];
        yield 'short key' => [['USER' => ['key' => base64_encode('short')]]];
        yield 'invalid key id' => [['USER' => ['keyId' => 'bad key']]];
        yield 'invalid key punctuation' => [['USER' => ['keyId' => '!bad']]];
        yield 'duplicate key id' => [['USER' => ['previousKeyId' => 'user-key', 'previousKey' => base64_encode(str_repeat('v', 32))]]];
        yield 'incomplete previous key' => [['USER' => ['previousKeyId' => 'old-user']]];
        yield 'wrong realm structure' => [['USER' => 'invalid']];
    }

    #[DataProvider('invalidLifetimes')]
    public function testSessionLifetimeRejectsCoercionAndInvalidBounds(mixed $value): void
    {
        $settings = IdentityFixture::settings(['tokenLifetimeSeconds' => $value]);
        $this->expectException(AuthenticationUnavailable::class);
        $settings->tokenLifetimeSeconds();
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidLifetimes(): iterable
    {
        yield 'too short' => [59];
        yield 'too long' => [3601];
        yield 'string' => ['900'];
    }

    public function testEncryptionRotationAcceptsOldFactorsAndWritesTheActiveKey(): void
    {
        $old       = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        $encrypted = $old->encrypt('factor-secret');
        $proof     = $old->proofHash('ownership-code');
        $settings  = IdentityFixture::settings(['encryptionKeyId' => 'new-factor', 'encryptionKey' => base64_encode(str_repeat('f', 32)), 'previousEncryptionKeyId' => 'factor-key', 'previousEncryptionKey' => base64_encode(str_repeat('e', 32))]);
        $rotated   = new SodiumAuthenticationSecrets($settings);
        self::assertSame('factor-secret', $rotated->decrypt($encrypted));
        self::assertTrue($rotated->verifyProof('ownership-code', $proof));
        self::assertStringStartsWith('new-factor:', $rotated->encrypt('factor-secret'));
        self::assertSame(900, $settings->tokenLifetimeSeconds());
        self::assertSame('identity@example.com', $settings->ownershipSender());
        self::assertSame(900, new IdentitySettings(new Settings([]))->tokenLifetimeSeconds());
    }

    public function testInvalidTopLevelConfigurationFailsClosed(): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        new IdentitySettings(new Settings(['identity' => 'invalid']));
    }
}
