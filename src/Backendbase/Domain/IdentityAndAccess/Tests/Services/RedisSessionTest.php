<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Redis;

use function filter_var;
use function getenv;
use function is_array;
use function time;

use const FILTER_VALIDATE_INT;

final class RedisSessionTest extends TestCase
{
    private Redis $client;
    private string $accountId;

    protected function setUp(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared Redis 8 target is required.');
        }

        $port = filter_var(getenv('BACKENDBASE_TEST_REDIS_PORT') ?: '6379', FILTER_VALIDATE_INT);
        self::assertIsInt($port);
        $password = getenv('BACKENDBASE_TEST_REDIS_PASSWORD');
        self::assertIsString($password);
        $this->client = new Redis();
        self::assertTrue($this->client->connect('127.0.0.1', $port, 2, read_timeout: 2));
        if ($password !== '') {
            self::assertTrue($this->client->auth($password));
        }

        $info = $this->client->info('server');
        self::assertIsArray($info);
        self::assertIsString($info['redis_version']);
        self::assertStringStartsWith('8.', $info['redis_version']);
        $this->accountId = IdentityScenario::id();
    }

    protected function tearDown(): void
    {
        if (! isset($this->client, $this->accountId)) {
            return;
        }

        $cursor = null;
        do {
            $keys = $this->client->scan($cursor, 'identity:*' . $this->accountId . '*', 100);
            if (! is_array($keys) || $keys === []) {
                continue;
            }

            $this->client->del($keys);
        } while ($cursor !== 0);

        $this->client->close();
    }

    public function testActivationChecksBothRecordsAndRejectsDuplicateSessionIdentity(): void
    {
        $registry  = $this->registry();
        $principal = $this->principal();
        $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
        $registry->assertActive($principal);
        $identity = $principal->identity();
        $key      = 'identity:{USER:' . $this->accountId . '}:jti:' . $identity['jti'];
        self::assertGreaterThan(0, $this->client->ttl($key));
        self::assertLessThanOrEqual(900, $this->client->ttl($key));
        $duplicate = new Principal(Realm::USER, [...$identity, 'jti' => IdentityScenario::id()]);
        $this->expectException(AuthenticationDenied::class);
        $registry->activate($duplicate, new DateTimeImmutable('+15 minutes'));
    }

    public function testTokenAndSessionRevocationAreIndependent(): void
    {
        $registry  = $this->registry();
        $principal = $this->principal();
        $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
        $registry->revokeToken($principal);
        $identity = $principal->identity();
        self::assertSame(1, $this->client->exists('identity:{USER:' . $this->accountId . '}:sid:' . $identity['sid']));
        $registry->revokeSession($principal);
        self::assertSame(0, $this->client->exists('identity:{USER:' . $this->accountId . '}:sid:' . $identity['sid']));
        $this->expectException(AuthenticationDenied::class);
        $registry->assertActive($principal);
    }

    public function testAccountRevocationBlocksExistingAndNewSessionsInBothRealms(): void
    {
        $registry = $this->registry();
        $user     = $this->principal();
        $admin    = $this->principal(Realm::ADMIN);
        $registry->activate($user, new DateTimeImmutable('+15 minutes'));
        $registry->activate($admin, new DateTimeImmutable('+15 minutes'));
        $registry->revokeAccount($this->accountId);
        foreach ([$user, $admin] as $principal) {
            try {
                $registry->assertActive($principal);
                self::fail('Account revocation must reject every realm.');
            } catch (AuthenticationDenied $exception) {
                self::assertSame('Authentication is not permitted.', $exception->getMessage());
            }
        }

        $this->expectException(AuthenticationDenied::class);
        $registry->activate($this->principal(), new DateTimeImmutable('+15 minutes'));
    }

    public function testIdentityMismatchAndExpirationFailClosed(): void
    {
        $registry  = $this->registry();
        $principal = $this->principal();
        $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
        $identity = $principal->identity();
        try {
            $registry->assertActive(new Principal(Realm::USER, [...$identity, 'authVersion' => 2]));
            self::fail('The stored authorization version must match.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }

        $this->client->expireAt('identity:{USER:' . $this->accountId . '}:sid:' . $identity['sid'], time() - 1);
        $this->expectException(AuthenticationDenied::class);
        $registry->assertActive($principal);
    }

    private function registry(): RedisTokenRegistry
    {
        return new RedisTokenRegistry(new SessionRedis(fn (): Redis => $this->client));
    }

    private function principal(Realm $realm = Realm::USER): Principal
    {
        return new Principal($realm, ['accountId' => $this->accountId, 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
    }
}
