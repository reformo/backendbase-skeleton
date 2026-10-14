<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RedisTokenRegistryTest extends TestCase
{
    #[DataProvider('realms')]
    public function testActivationSendsBoundedRealmScopedTokenAndSessionRecords(Realm $realm): void
    {
        $principal     = self::principal($realm);
        $claims        = $principal->claims();
        $identity      = $principal->identity();
        $prefix        = 'identity:{' . $realm->value . ':' . $identity['accountId'] . '}:';
        $sessionClaims = $claims;
        unset($sessionClaims['jti']);
        $expiresAt  = new DateTimeImmutable('2026-10-15T10:15:00Z');
        $expiration = $expiresAt->getTimestamp();
        $client     = $this->createMock(RedisJsonInterface::class);
        $evaluation = $client->expects(self::once());
        $evaluation->method('raw');
        $evaluation->with(
            'EVAL',
            self::isString(),
            3,
            $prefix . 'jti:' . $identity['jti'],
            $prefix . 'sid:' . $identity['sid'],
            $prefix . 'blocked',
            json_encode($claims, JSON_THROW_ON_ERROR),
            json_encode($sessionClaims, JSON_THROW_ON_ERROR),
            $expiration,
        );
        $evaluation->willReturn(1);
        $registry = new RedisTokenRegistry($client);
        $registry->activate($principal, $expiresAt);
    }

    public function testAnActivationRejectedByRedisCannotIssueASession(): void
    {
        $client     = $this->createMock(RedisJsonInterface::class);
        $evaluation = $client->expects(self::once());
        $evaluation->method('raw');
        $evaluation->willReturn(0);
        $registry = new RedisTokenRegistry($client);
        $this->expectException(AuthenticationDenied::class);
        $registry->activate(self::principal(Realm::USER), new DateTimeImmutable('2026-10-15T10:15:00Z'));
    }

    #[DataProvider('storedRecords')]
    public function testSessionChecksRequireBothMatchingRecordsAndNoAccountBlock(string $state): void
    {
        $principal = self::principal(Realm::USER);
        $claims    = $principal->claims();
        $token     = json_encode($claims, JSON_THROW_ON_ERROR);
        unset($claims['jti']);
        $session = json_encode($claims, JSON_THROW_ON_ERROR);
        $values  = match ($state) {
            'active' => [$token, $session, 0],
            'missing token' => [false, $session, 0],
            'missing session' => [$token, false, 0],
            'identity mismatch' => [$session, $session, 0],
            'blocked Account' => [$token, $session, 1],
            default => 'invalid',
        };
        $client     = $this->createMock(RedisJsonInterface::class);
        $evaluation = $client->expects(self::once());
        $evaluation->method('raw');
        $evaluation->willReturn($values);
        $registry = new RedisTokenRegistry($client);
        if ($state !== 'active') {
            $this->expectException(AuthenticationDenied::class);
        }

        $registry->assertActive($principal);
    }

    public function testRevocationSelectsTheTokenSessionAndBothAccountRealms(): void
    {
        $principal  = self::principal(Realm::USER);
        $identity   = $principal->identity();
        $prefix     = 'identity:{USER:' . $identity['accountId'] . '}:';
        $client     = $this->createMock(RedisJsonInterface::class);
        $calls      = [];
        $evaluation = $client->expects(self::exactly(4));
        $evaluation->method('raw');
        $evaluation->willReturnCallback(static function (string $command, string $script, int $keyCount, string|int ...$arguments) use (&$calls): int {
            $calls[] = [$script, $arguments, $keyCount];

            return 1;
        });
        $registry = new RedisTokenRegistry($client);
        $registry->revokeToken($principal);
        $registry->revokeSession($principal);
        $registry->revokeAccount($identity['accountId']);
        self::assertSame([
            ["return redis.call('DEL', KEYS[1])", [$prefix . 'jti:' . $identity['jti']], 1],
            ["return redis.call('DEL', KEYS[1], KEYS[2])", [$prefix . 'jti:' . $identity['jti'], $prefix . 'sid:' . $identity['sid']], 2],
            ["return redis.call('SET', KEYS[1], '1', 'EX', 3600)", [$prefix . 'blocked'], 1],
            ["return redis.call('SET', KEYS[1], '1', 'EX', 3600)", ['identity:{ADMIN:' . $identity['accountId'] . '}:blocked'], 1],
        ], $calls);
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'User' => [Realm::USER];
        yield 'Admin' => [Realm::ADMIN];
    }

    /** @return iterable<string, array{string}> */
    public static function storedRecords(): iterable
    {
        foreach (['active', 'missing token', 'missing session', 'identity mismatch', 'blocked Account', 'malformed reply'] as $state) {
            yield $state => [$state];
        }
    }

    private static function principal(Realm $realm): Principal
    {
        return new Principal($realm, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
    }
}
