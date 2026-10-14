<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services\RedisJson;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedRedis;
use Tests\Support\Services\RedisFailover\AuthenticationAssertions;
use Tests\Support\Services\RedisFailover\Eventually;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RedisJsonSessionTest extends TestCase
{
    private Principal $principal;
    private IsolatedRedis $fixture;

    protected function setUp(): void
    {
        $this->principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $principal       = $this->principal;
        $identity        = $principal->identity();
        $this->fixture   = IsolatedRedis::create($identity['accountId']);
    }

    protected function tearDown(): void
    {
        if (! isset($this->fixture)) {
            return;
        }

        $fixture = $this->fixture;
        $fixture->destroy();
    }

    #[DataProvider('realms')]
    public function testBothJsonRecordsKeepClaimsAndExpireWithoutRenewal(Realm $realm): void
    {
        $current   = $this->principal;
        $principal = new Principal($realm, $current->identity());
        $fixture   = $this->fixture;
        $json      = $fixture->json();
        $client    = $fixture->client();
        $registry  = new RedisTokenRegistry($json);
        $expiresAt = new DateTimeImmutable('+15 minutes');
        $registry->activate($principal, $expiresAt);
        [$token, $session] = $fixture->keys($principal);
        $claims            = $principal->claims();
        self::assertSame('object', $json->type($token));
        self::assertSame($claims, $json->get($token));
        unset($claims['jti']);
        self::assertSame('object', $json->type($session));
        self::assertSame($claims, $json->get($session));
        $expiration = $expiresAt->getTimestamp();
        self::assertSame($expiration, $client->rawCommand('EXPIRETIME', $token));
        self::assertSame($expiration, $client->rawCommand('EXPIRETIME', $session));
        $remaining = $client->pttl($token);
        self::assertGreaterThan(0, $remaining);
        self::assertLessThanOrEqual(900000, $remaining);
        $registry->assertActive($principal);
        self::assertLessThanOrEqual($remaining, $client->pttl($token));
        $tokenExpiration = $client->rawCommand('PEXPIRETIME', $token);
        self::assertSame($tokenExpiration, $client->rawCommand('PEXPIRETIME', $session));
    }

    #[DataProvider('realms')]
    public function testRedisAutomaticallyRemovesBothExpiredJsonRecords(Realm $realm): void
    {
        $current   = $this->principal;
        $principal = new Principal($realm, $current->identity());
        $fixture   = $this->fixture;
        $registry  = new RedisTokenRegistry($fixture->json());
        $client    = $fixture->client();
        $registry->activate($principal, new DateTimeImmutable('+2 seconds'));
        [$token, $session] = $fixture->keys($principal);
        self::assertSame(2, $client->exists($token, $session));
        Eventually::until(static fn (): bool => $client->exists($token, $session) === 0, 'both JSON records expire', 4);
        AuthenticationAssertions::denied(static fn () => $registry->assertActive($principal));
    }

    #[DataProvider('realms')]
    public function testExistingStringRecordsRemainValidWithoutExtendingTheirLifetime(Realm $realm): void
    {
        $current           = $this->principal;
        $principal         = new Principal($realm, $current->identity());
        $fixture           = $this->fixture;
        $client            = $fixture->client();
        [$token, $session] = $fixture->keys($principal);
        $claims            = $principal->claims();
        self::assertTrue($client->set($token, json_encode($claims, JSON_THROW_ON_ERROR), ['ex' => 60]));
        unset($claims['jti']);
        self::assertTrue($client->set($session, json_encode($claims, JSON_THROW_ON_ERROR), ['ex' => 60]));
        $deadline = $client->rawCommand('PEXPIRETIME', $token);
        $registry = new RedisTokenRegistry($fixture->json());
        $registry->assertActive($principal);
        self::assertSame($deadline, $client->rawCommand('PEXPIRETIME', $token));
        $registry->revokeToken($principal);
        AuthenticationAssertions::denied(static fn () => $registry->assertActive($principal));
        $registry->revokeSession($principal);
        self::assertSame(0, $client->exists($token, $session));
    }

    public function testAnExpiredDeadlineCannotCreatePersistentRecords(): void
    {
        $fixture           = $this->fixture;
        $registry          = new RedisTokenRegistry($fixture->json());
        $client            = $fixture->client();
        [$token, $session] = $fixture->keys($this->principal);
        $this->expectException(AuthenticationDenied::class);
        try {
            $registry->activate($this->principal, new DateTimeImmutable('-1 second'));
        } finally {
            self::assertSame(0, $client->exists($token, $session));
        }
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'USER' => [Realm::USER];
        yield 'ADMIN' => [Realm::ADMIN];
    }
}
