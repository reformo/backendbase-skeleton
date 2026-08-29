<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtAuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenCodec;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenConfiguration;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Shared\Configuration\JwtSettings;
use Backendbase\Shared\Services\Settings;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use ReflectionMethod;
use UnexpectedValueException;

use function array_key_exists;
use function base64_encode;

class JwtTest extends TestCase
{
    #[Test]
    public function itValidatesIssuedTokensAndRejectsRevokedTokens(): void
    {
        $redisJson = $this->redisJson();
        $jwt       = $this->jwt($redisJson, 'user-api');

        $token = $jwt->issueNewToken('userId', 'user-123', [
            'uuid' => 'user-123',
            'email' => 'user@example.com',
        ]);

        $tokenData = $jwt->validateToken($token);
        $this->assertSame('user-123', $tokenData['user']['uuid']);

        $jwt->revokeToken($token);

        $this->expectException(AuthorizationExpired::class);

        $jwt->validateToken($token);
    }

    #[Test]
    public function itRejectsTokensIssuedForADifferentAudience(): void
    {
        $redisJson      = $this->redisJson();
        $tokenIssuer    = $this->jwt($redisJson, 'user-api');
        $tokenValidator = $this->jwt($redisJson, 'example-api');

        $token = $tokenIssuer->issueNewToken('userId', 'user-123', [
            'uuid' => 'user-123',
            'email' => 'user@example.com',
        ]);

        $this->expectException(AuthorizationExpired::class);

        $tokenValidator->validateToken($token);
    }

    #[Test]
    public function itUsesTheCurrentTimeWhenIssuingFromALongRunningService(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-25T10:00:00+00:00'));
        $jwt   = $this->jwt($this->redisJson(), 'user-api', $clock);
        $clock->adjustTime('+2 hours');

        $token = $jwt->issueNewToken('userId', 'user-123', ['uuid' => 'user-123']);
        if ($token === '') {
            self::fail('The issued token must not be empty.');
        }

        $parsedToken = new Parser(new JoseEncoder())->parse($token);
        self::assertInstanceOf(UnencryptedToken::class, $parsedToken);
        $issuedAt  = $parsedToken->claims()->get(RegisteredClaims::ISSUED_AT);
        $expiresAt = $parsedToken->claims()->get(RegisteredClaims::EXPIRATION_TIME);

        self::assertInstanceOf(DateTimeImmutable::class, $issuedAt);
        self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
        self::assertSame($clock->now()->getTimestamp(), $issuedAt->getTimestamp());
        self::assertSame(
            $clock->now()->add(new DateInterval('PT24H'))->getTimestamp(),
            $expiresAt->getTimestamp(),
        );
    }

    #[Test]
    public function itRejectsAnExpiredTokenOutsideTheClockLeeway(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-25T10:00:00+00:00'));
        $jwt   = $this->jwt($this->redisJson(), 'user-api', $clock);
        $token = $jwt->issueNewToken('userId', 'user-123', ['uuid' => 'user-123']);

        $clock->adjustTime('+24 hours 29 seconds');
        $tokenData = $jwt->validateToken($token);
        self::assertSame('user-123', $tokenData['user']['uuid']);

        $clock->adjustTime('+2 seconds');
        $this->expectException(AuthorizationExpired::class);

        $jwt->validateToken($token);
    }

    #[Test]
    public function itRejectsAnEmptyClaimKey(): void
    {
        $jwt = $this->jwt($this->redisJson(), 'user-api');

        $this->expectException(InvalidArgumentException::class);

        $jwt->issueNewToken('', 'user-123', []);
    }

    #[Test]
    public function itStoresPrivilegesDatesAndLegacyTokenCollections(): void
    {
        $state = new JwtRedisState();
        $state->put('user:user-123', ['name' => 'Existing user']);
        $jwt  = $this->jwt($this->redisJson($state), 'user-api');
        $date = new DateTimeImmutable('2026-08-25T10:00:00+00:00');

        $token = $jwt->issueNewToken('userId', 'user-123', [
            'createdAt' => $date,
            'privileges' => ['examples.read'],
        ]);

        $stored = $state->jsonValues['user:user-123'];
        self::assertIsArray($stored);
        self::assertSame($token, $stored['tokens'][0]['token']);
        self::assertIsString($stored['tokens'][0]['jti']);
        self::assertSame(['examples.read'], $stored['privileges']);
        self::assertSame('userId', $stored['claimKey']);
    }

    #[Test]
    public function itRejectsInvalidAuthorizationPrivileges(): void
    {
        $jwt = $this->jwt($this->redisJson(), 'user-api');

        $this->expectException(UnexpectedValueException::class);

        $jwt->issueNewToken('userId', 'user-123', ['privileges' => ['duplicate', 'duplicate']]);
    }

    #[Test]
    public function itRejectsMalformedStoredAuthorizationState(): void
    {
        foreach (['invalid', ['tokens' => 'invalid']] as $storedState) {
            $state                              = new JwtRedisState();
            $state->jsonValues['user:user-123'] = $storedState;
            $jwt                                = $this->jwt($this->redisJson($state), 'user-api');

            try {
                $jwt->issueNewToken('userId', 'user-123', []);
                self::fail('Malformed authorization state must fail token issue.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itRejectsAValidTokenWhenItsUserDataIsMissing(): void
    {
        $state = new JwtRedisState();
        $jwt   = $this->jwt($this->redisJson($state), 'user-api');
        $token = $jwt->issueNewToken('userId', 'user-123', []);
        unset($state->jsonValues['user:user-123']);

        $this->expectException(AuthorizationExpired::class);

        $jwt->validateToken($token);
    }

    #[Test]
    public function itRejectsAValidTokenWithoutTheUserIdentifierClaim(): void
    {
        $jwt   = $this->jwt($this->redisJson(), 'user-api');
        $token = $jwt->issueNewToken('subject', 'user-123', []);

        $this->expectException(AuthorizationExpired::class);

        $jwt->validateToken($token);
    }

    #[Test]
    public function itValidatesStoredAuthorizationByUserId(): void
    {
        $state                              = new JwtRedisState();
        $state->jsonValues['user:user-123'] = ['user' => ['uuid' => 'user-123']];
        $jwt                                = $this->jwt($this->redisJson($state), 'user-api');

        self::assertSame(
            ['user' => ['uuid' => 'user-123']],
            $jwt->validateByUserId('user-123'),
        );

        $this->expectException(AuthorizationExpired::class);

        $jwt->validateByUserId('missing');
    }

    #[Test]
    public function itRevokesAllAuthorizationStateForAnAccount(): void
    {
        $state = new JwtRedisState();
        $store = $this->authorizationStore($state);
        $jwt   = $this->jwt($this->redisJson($state), 'user-api');
        $jwt->issueNewToken('userId', '4bb3fe29-8b80-463e-9d42-b3a9298a7586', ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586']);

        $store->revokeAll(AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586'));

        self::assertArrayNotHasKey('user:4bb3fe29-8b80-463e-9d42-b3a9298a7586', $state->jsonValues);
    }

    #[Test]
    public function itRejectsEmptyAndMalformedTokensDuringRevocation(): void
    {
        $jwt = $this->jwt($this->redisJson(), 'user-api');

        $this->expectException(AuthorizationExpired::class);

        $jwt->revokeToken('');
    }

    #[Test]
    public function itRejectsATokenWithoutAnIdentifier(): void
    {
        $signKey = self::jwtConfig('user-api')['sign-key'];
        if ($signKey === '') {
            self::fail('The signing key must not be empty.');
        }

        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($signKey),
        );
        $token         = $configuration->builder()
            ->issuedAt(new DateTimeImmutable('2026-08-25T10:00:00+00:00'))
            ->getToken($configuration->signer(), $configuration->signingKey());
        $method        = new ReflectionMethod(Jwt::class, 'tokenId');

        $this->expectException(AuthorizationExpired::class);

        $method->invoke(null, $token);
    }

    /** @return array<string, string> */
    private static function jwtConfig(string $permittedFor): array
    {
        return [
            'alias' => 'USER',
            'issuer' => 'backendbase-identity-api',
            'identifier' => 'H2i0W2e6llEU',
            'permitted-for' => $permittedFor,
            'sign-key' => base64_encode('test-signing-key-32-bytes-long!!!'),
            'duration' => 'PT24H',
        ];
    }

    private function jwt(RedisJsonInterface $redisJson, string $permittedFor, FrozenClock|null $clock = null): Jwt
    {
        $settings      = new Settings(['jwt' => self::jwtConfig($permittedFor)]);
        $configuration = new JwtTokenConfiguration(new JwtSettings($settings));

        return new Jwt(
            new JwtTokenCodec($configuration, $clock ?? SystemClock::fromUTC()),
            new JwtAuthorizationStore($redisJson, $configuration),
        );
    }

    private function authorizationStore(JwtRedisState $state): JwtAuthorizationStore
    {
        $settings      = new Settings(['jwt' => self::jwtConfig('user-api')]);
        $configuration = new JwtTokenConfiguration(new JwtSettings($settings));

        return new JwtAuthorizationStore($this->redisJson($state), $configuration);
    }

    private function redisJson(JwtRedisState|null $store = null): RedisJsonInterface
    {
        $store ??= new JwtRedisState();
        $client = new class {
            /** @var array<string, mixed> */
            public array $values = [];

            public function set(string $key, mixed $value, int $ttl = 0): bool
            {
                $this->values[$key] = $value;

                return true;
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? false;
            }

            public function expire(string $key, int $ttl): bool
            {
                return $key !== '' && $ttl > 0;
            }

            public function del(string $key): int
            {
                $exists = array_key_exists($key, $this->values);
                unset($this->values[$key]);

                return $exists ? 1 : 0;
            }
        };

        $redisJson = $this->createStub(RedisJsonInterface::class);
        $redisJson->method('getClient')->willReturn($client);
        $redisJson->method('get')->willReturnCallback(static function (string $key) use ($store): mixed {
            return $store->jsonValues[$key] ?? null;
        });
        $redisJson->method('set')->willReturnCallback(
            static function (string $key, string $path, mixed $json) use ($store): string {
                $store->jsonValues[$key] = $json;

                return 'OK';
            },
        );
        $redisJson->method('del')->willReturnCallback(static function (string $key) use ($store): int {
            $exists = array_key_exists($key, $store->jsonValues);
            unset($store->jsonValues[$key]);

            return $exists ? 1 : 0;
        });

        return $redisJson;
    }
}
