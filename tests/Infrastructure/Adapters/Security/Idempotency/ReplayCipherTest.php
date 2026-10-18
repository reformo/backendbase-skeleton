<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Security\Idempotency;

use Backendbase\Infrastructure\Adapters\Security\Idempotency\SodiumRequestReplayCipher;
use Backendbase\Infrastructure\Configuration\Idempotency\ReplaySettings;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Services\Settings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Http\ReplayFixture;

use function base64_encode;
use function hash;
use function str_repeat;

final class ReplayCipherTest extends TestCase
{
    public function testResponsesAreEncryptedAndBoundToTheirRequest(): void
    {
        $cipher    = new SodiumRequestReplayCipher(ReplayFixture::settings());
        $identity  = new RequestIdentity(hash('sha256', 'request'), $cipher->fingerprint('secret-password'));
        $encrypted = $cipher->encrypt($identity, '{"accessToken":"secret-token"}');
        self::assertStringNotContainsString('secret-token', $encrypted);
        self::assertSame('{"accessToken":"secret-token"}', $cipher->decrypt($identity, $encrypted));
        self::assertNotSame($encrypted, $cipher->encrypt($identity, '{"accessToken":"secret-token"}'));
        self::assertNotSame(hash('sha256', 'secret-password'), $identity->fingerprint);
        $this->expectException(RuntimeException::class);
        $different = new RequestIdentity(hash('sha256', 'another-request'), $identity->fingerprint);
        $cipher->decrypt($different, $encrypted);
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidConfigurationHasNoFallbackSecret(mixed $value): void
    {
        $settings = new ReplaySettings(new Settings(['idempotency' => ['key' => $value]]));
        $this->expectException(InvalidArgumentException::class);
        $settings->key();
    }

    #[DataProvider('invalidCiphertext')]
    public function testCorruptCiphertextIsRejected(string $value): void
    {
        $cipher   = new SodiumRequestReplayCipher(ReplayFixture::settings());
        $identity = new RequestIdentity(hash('sha256', 'key'), hash('sha256', 'body'));
        $this->expectException(RuntimeException::class);
        $cipher->decrypt($identity, $value);
    }

    public function testInvalidPersistedRequestDigestsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestIdentity('invalid', hash('sha256', 'body'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidKeys(): iterable
    {
        yield 'missing' => [null];
        yield 'type' => [42];
        yield 'encoding' => ['!'];
        yield 'length' => [base64_encode(str_repeat('x', 31))];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCiphertext(): iterable
    {
        yield 'base64' => ['!'];
        yield 'short' => [base64_encode('short')];
        yield 'tampered' => [base64_encode(str_repeat('x', 80))];
    }
}
