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
use Redis;
use Redislabs\Module\RedisJson\RedisJson;
use Tests\Support\Services\IsolatedRedis;
use Tests\Support\Services\RedisFailover\AuthenticationAssertions;

final class RedisJsonAtomicityTest extends TestCase
{
    #[DataProvider('collisions')]
    public function testConflictsCannotOverwriteRecordsOrLeaveHalfASession(Realm $realm, string $changedField): void
    {
        $principal = new Principal($realm, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $identity  = $principal->identity();
        $fixture   = IsolatedRedis::create($identity['accountId']);
        try {
            self::assertCollision($fixture, $principal, $changedField);
        } finally {
            $fixture->destroy();
        }
    }

    #[DataProvider('deniedCommands')]
    public function testFailedJsonCreationOrExpiryRemovesBothNewRecords(string $deniedCommand): void
    {
        $principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $identity  = $principal->identity();
        $fixture   = IsolatedRedis::create($identity['accountId']);
        $client    = $fixture->client();
        $username  = 'redis-json-test-' . $identity['accountId'];
        try {
            self::assertTrue($client->rawCommand('ACL', 'SETUSER', $username, 'on', '>test-only-password', '~identity:{USER:' . $identity['accountId'] . '}:*', '+@all', '-' . $deniedCommand));
            self::assertFailedActivation($fixture, $principal, $username);
        } finally {
            $client->rawCommand('ACL', 'DELUSER', $username);
            $fixture->destroy();
        }
    }

    private static function assertCollision(IsolatedRedis $fixture, Principal $principal, string $changedField): void
    {
        $registry = new RedisTokenRegistry($fixture->json());
        $client   = $fixture->client();
        $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
        [$token, $session]       = $fixture->keys($principal);
        $tokenSnapshot           = $client->dump($token);
        $sessionSnapshot         = $client->dump($session);
        $deadline                = $client->rawCommand('PEXPIRETIME', $token);
        $realm                   = $principal->realm();
        $identity                = $principal->identity();
        $candidate               = new Principal($realm, [...$identity, $changedField => IdentityScenario::id()]);
        [$newToken, $newSession] = $fixture->keys($candidate);
        try {
            $registry->activate($candidate, new DateTimeImmutable('+30 minutes'));
            self::fail('A duplicate token or session must be rejected.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('The session could not be activated.', $exception->getMessage());
        }

        $currentToken    = $client->dump($token);
        $currentSession  = $client->dump($session);
        $currentDeadline = $client->rawCommand('PEXPIRETIME', $token);
        self::assertSame([$tokenSnapshot, $sessionSnapshot, $deadline], [$currentToken, $currentSession, $currentDeadline]);
        $orphan = $changedField === 'jti' ? $newToken : $newSession;
        self::assertSame(0, $client->exists($orphan));
        $registry->assertActive($principal);
    }

    private static function assertFailedActivation(IsolatedRedis $fixture, Principal $principal, string $username): void
    {
        $client     = $fixture->client();
        $port       = $client->getPort();
        $restricted = new Redis();
        self::assertTrue($restricted->connect('127.0.0.1', $port, 2, read_timeout: 2));
        try {
            self::assertTrue($restricted->auth([$username, 'test-only-password']));
            $registry = new RedisTokenRegistry(RedisJson::createWithPhpRedis($restricted));
            AuthenticationAssertions::unavailable(static fn () => $registry->activate($principal, new DateTimeImmutable('+15 minutes')));
            [$token, $session] = $fixture->keys($principal);
            self::assertSame(0, $client->exists($token, $session));
        } finally {
            $restricted->close();
        }
    }

    /** @return iterable<string, array{Realm, string}> */
    public static function collisions(): iterable
    {
        yield 'USER duplicate session' => [Realm::USER, 'jti'];
        yield 'USER duplicate token' => [Realm::USER, 'sid'];
        yield 'ADMIN duplicate session' => [Realm::ADMIN, 'jti'];
        yield 'ADMIN duplicate token' => [Realm::ADMIN, 'sid'];
    }

    /** @return iterable<string, array{string}> */
    public static function deniedCommands(): iterable
    {
        yield 'JSON.SET denied' => ['json.set'];
        yield 'EXPIRE denied' => ['expire'];
        yield 'EXPIREAT denied' => ['expireat'];
    }
}
