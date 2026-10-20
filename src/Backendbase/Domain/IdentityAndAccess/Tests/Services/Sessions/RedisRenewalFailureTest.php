<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services\Sessions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use Redislabs\Module\RedisJson\RedisJson;
use Tests\Support\Services\IsolatedRedis;
use Tests\Support\Services\RedisFailover\AuthenticationAssertions;

final class RedisRenewalFailureTest extends TestCase
{
    #[DataProvider('deniedCommands')]
    public function testRotationWriteFailureLeavesNoUsableSessionOrReplacementToken(string $deniedCommand): void
    {
        $principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $identity  = $principal->identity();
        $fixture   = IsolatedRedis::create($identity['accountId']);
        $client    = $fixture->client();
        $username  = 'session-rotation-test-' . $identity['accountId'];
        try {
            self::assertTrue($client->rawCommand('ACL', 'SETUSER', $username, 'on', '>test-only-password', '~identity:{USER:' . $identity['accountId'] . '}:*', '+@all', '-' . $deniedCommand));
            self::assertFailedRotation($fixture, $principal, $username);
        } finally {
            $client->rawCommand('ACL', 'DELUSER', $username);
            $fixture->destroy();
        }
    }

    private static function assertFailedRotation(IsolatedRedis $fixture, Principal $principal, string $username): void
    {
        $registry = new RedisTokenRegistry($fixture->json());
        $now      = new DateTimeImmutable();
        $refresh  = RefreshToken::create($principal);
        $session  = Session::start($principal, new SessionPolicy(60, 120, 600), $now, $refresh);
        $expires  = $session->tokenExpiresAt($now);
        $registry->activate($principal, $expires, $session->snapshot());
        $session->rotate(RefreshToken::create($principal), $now);
        $replacement = $session->snapshot();
        $client      = $fixture->client();
        $port        = $client->getPort();
        $restricted  = new Redis();
        self::assertTrue($restricted->connect('127.0.0.1', $port, 2, read_timeout: 2));
        try {
            self::assertTrue($restricted->auth([$username, 'test-only-password']));
            $limitedRegistry = new RedisTokenRegistry(RedisJson::createWithPhpRedis($restricted));
            AuthenticationAssertions::unavailable(static fn () => $limitedRegistry->rotate($refresh, $replacement, $expires));
            [$token, $sessionKey] = $fixture->keys($replacement->principal);
            self::assertSame(0, $client->exists($token, $sessionKey));
            AuthenticationAssertions::denied(static fn () => $registry->assertActive($principal));
        } finally {
            $restricted->close();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function deniedCommands(): iterable
    {
        yield 'token JSON creation' => ['json.set'];
        yield 'token expiry' => ['expire'];
        yield 'absolute expiry' => ['expireat'];
        yield 'consumed marker' => ['set'];
    }
}
