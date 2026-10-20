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
        yield 'shared issuer' => [['admin' => ['issuer' => 'https://user.example.com']]];
        yield 'shared audience' => [['admin' => ['audience' => 'user-api']]];
        yield 'shared key material' => [['admin' => ['key' => base64_encode(str_repeat('u', 32))]]];
        yield 'missing field' => [['user' => ['issuer' => '']]];
        yield 'control character' => [['user' => ['issuer' => "issuer\n"]]];
        yield 'invalid base64' => [['user' => ['key' => '!']]];
        yield 'short key' => [['user' => ['key' => base64_encode('short')]]];
        yield 'invalid key id' => [['user' => ['key-id' => 'bad key']]];
        yield 'invalid key punctuation' => [['user' => ['key-id' => '!bad']]];
        yield 'duplicate key id' => [['user' => ['previous-key-id' => 'user-key', 'previous-key' => base64_encode(str_repeat('v', 32))]]];
        yield 'incomplete previous key' => [['user' => ['previous-key-id' => 'old-user']]];
        yield 'wrong realm structure' => [['user' => 'invalid']];
    }

    public function testProofKeyRotationAcceptsOldProofsAndWritesTheActiveKey(): void
    {
        $old      = new SodiumAuthenticationSecrets(IdentityFixture::settings());
        $proof    = $old->proofHash('ownership-code');
        $settings = IdentityFixture::settings(['contact-proof-key-id' => 'new-factor', 'contact-proof-key' => base64_encode(str_repeat('f', 32)), 'previous-contact-proof-key-id' => 'factor-key', 'previous-contact-proof-key' => base64_encode(str_repeat('e', 32))]);
        $rotated  = new SodiumAuthenticationSecrets($settings);
        self::assertTrue($rotated->verifyProof('ownership-code', $proof));
        self::assertStringStartsWith('new-factor:', $rotated->proofHash('ownership-code'));
        self::assertSame('identity@example.com', $settings->contactEmailSender());
    }

    public function testInvalidTopLevelConfigurationFailsClosed(): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        new IdentitySettings(new Settings(['identity' => 'invalid']));
    }
}
