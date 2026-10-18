<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RealmTokenCodec;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

use function base64_encode;
use function hash_hmac;
use function json_encode;
use function rtrim;
use function str_repeat;
use function strtr;

use const JSON_THROW_ON_ERROR;

final class RealmTokenCodecTest extends TestCase
{
    public function testBothRealmsRoundTripAndRejectTokensFromTheOtherRealm(): void
    {
        $codec     = self::codec();
        $principal = self::principal(Realm::ADMIN);
        $token     = $codec->encode($principal, new DateTimeImmutable('2026-10-14T10:15:00Z'));
        self::assertSame($principal->claims(), $codec->decode(Realm::ADMIN, $token)->claims());
        $this->expectException(AuthenticationDenied::class);
        $codec->decode(Realm::USER, $token);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidClaims')]
    public function testCorrectlySignedTokensMustMatchTheWholeRouteTrustPolicy(array $changes): void
    {
        $this->expectException(AuthenticationDenied::class);
        self::codec()->decode(Realm::USER, self::signed($changes));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidClaims(): iterable
    {
        yield 'issuer' => [['iss' => 'other-issuer']];
        yield 'audience' => [['aud' => ['other-audience']]];
        yield 'expired' => [['exp' => 1791971999]];
        yield 'future not before' => [['nbf' => 1891972000]];
        yield 'realm' => [['realm' => 'ADMIN']];
        yield 'actor type' => [['actorType' => 'SystemAdmin']];
        yield 'version type' => [['authVersion' => '1']];
        yield 'version range' => [['authVersion' => 0]];
        yield 'missing identity' => [['sid' => null]];
        yield 'invalid identity' => [['sub' => 'invalid']];
        yield 'empty identity' => [['accountId' => '']];
        yield 'noncanonical identity' => [['sub' => '019F6322-1234-7000-8000-000000000001']];
    }

    /** @param array<string, mixed> $headers */
    #[DataProvider('invalidHeaders')]
    public function testUntrustedHeadersCannotChangeSigningAlgorithmOrKeySelection(array $headers): void
    {
        $this->expectException(AuthenticationDenied::class);
        self::codec()->decode(Realm::USER, self::signed([], $headers));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidHeaders(): iterable
    {
        yield 'unknown kid' => [['kid' => 'unknown']];
        yield 'numeric kid' => [['kid' => 1]];
        yield 'type' => [['typ' => 'JWE']];
        yield 'algorithm' => [['alg' => 'HS512']];
    }

    #[DataProvider('malformedTokens')]
    public function testMalformedOversizedAndUnsignedTokensAreDenied(string $token): void
    {
        $this->expectException(AuthenticationDenied::class);
        self::codec()->decode(Realm::USER, $token);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'oversized' => [str_repeat('a', 8193)];
        yield 'invalid serialization' => ['invalid.token'];
        yield 'missing signature' => ['eyJhbGciOiJub25lIn0.e30.'];
    }

    public function testPreviousRealmKeyWorksOnlyDuringItsConfiguredOverlap(): void
    {
        $old   = self::codec()->encode(self::principal(), new DateTimeImmutable('2026-10-14T10:15:00Z'));
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $keys  = ['key-id' => 'user-key-2', 'key' => base64_encode(str_repeat('v', 32))];
        $codec = new RealmTokenCodec(IdentityFixture::settings(['user' => [...$keys, 'previous-key-id' => 'user-key', 'previous-key' => base64_encode(str_repeat('u', 32))]]), $clock);
        self::assertSame(Realm::USER, $codec->decode(Realm::USER, $old)->realm());
        $retired = new RealmTokenCodec(IdentityFixture::settings(['user' => $keys]), $clock);
        $this->expectException(AuthenticationDenied::class);
        $retired->decode(Realm::USER, $old);
    }

    private static function codec(): RealmTokenCodec
    {
        return new RealmTokenCodec(IdentityFixture::settings(), new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z')));
    }

    private static function principal(Realm $realm = Realm::USER): Principal
    {
        return new Principal($realm, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $headers
     */
    private static function signed(array $changes, array $headers = []): string
    {
        $principal = self::principal();
        $now       = new DateTimeImmutable('2026-10-14T10:00:00Z')->getTimestamp();
        $claims    = [...$principal->claims(), 'iss' => 'https://user.example.com', 'aud' => ['user-api'], 'iat' => $now, 'nbf' => $now, 'exp' => $now + 900, ...$changes];
        $encode    = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payload   = $encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => 'user-key', ...$headers]) . '.' . $encode($claims);

        return $payload . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, str_repeat('u', 32), true)), '+/', '-_'), '=');
    }
}
