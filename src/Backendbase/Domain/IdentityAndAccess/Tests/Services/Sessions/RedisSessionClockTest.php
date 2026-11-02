<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services\Sessions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedRedis;

final class RedisSessionClockTest extends TestCase
{
    #[DataProvider('realms')]
    public function testRedisRejectsAClockEarlierThanLastActivity(Realm $realm): void
    {
        $accountId = IdentityScenario::id();
        $principal = new Principal($realm, ['accountId' => $accountId, 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $fixture   = IsolatedRedis::create($accountId);
        try {
            $registry  = new RedisTokenRegistry($fixture->json());
            $now       = new DateTimeImmutable();
            $refresh   = RefreshToken::create($principal);
            $session   = Session::start($principal, new SessionPolicy(60, 120, 600), $now, $refresh);
            $expiresAt = $session->tokenExpiresAt($now);
            $state     = $session->snapshot();
            $registry->activate($principal, $expiresAt, $state);
            [, $sessionKey] = $fixture->keys($principal);
            $client         = $fixture->client();
            $future         = $now->getTimestamp() + 30;
            $updated        = $client->rawCommand('JSON.SET', $sessionKey, '$.lastUsedAt', (string) $future);
            self::assertContains($updated, ['OK', true]);
            self::denied(static fn () => $registry->assertActive($principal));
            self::denied(static fn () => $registry->session($realm, $refresh));
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'User' => [Realm::USER];
        yield 'Admin' => [Realm::ADMIN];
    }

    private static function denied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Redis must reject activity from the future.');
        } catch (AuthenticationDenied $exception) {
            self::assertNotEmpty($exception->getMessage());
        }
    }
}
