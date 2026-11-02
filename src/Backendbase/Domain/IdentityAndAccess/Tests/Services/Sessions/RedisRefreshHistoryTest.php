<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services\Sessions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\SessionRecords;
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

final class RedisRefreshHistoryTest extends TestCase
{
    #[DataProvider('realms')]
    public function testMissingUsedMarkerDeniesOldCredentialWithoutRevokingCurrentSession(Realm $realm): void
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
            $initial   = $session->snapshot();
            $registry->activate($principal, $expiresAt, $initial);
            $replacement = RefreshToken::create($principal);
            $session->rotate($replacement, $now);
            $state = $session->snapshot();
            $registry->rotate($refresh, $state, $expiresAt);

            [, $marker] = SessionRecords::refreshKeys($realm, $refresh);
            $client     = $fixture->client();
            self::assertSame(1, $client->del($marker));
            self::denied(static fn () => $registry->session($realm, $refresh));
            self::denied(static fn () => $registry->session($realm, $refresh, true));
            $current = $registry->session($realm, $replacement);
            self::assertSame($state->refreshHash, $current->refreshHash);
            $currentPrincipal = $state->principal;
            $registry->assertActive($currentPrincipal);
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
            self::fail('The old refresh credential must be denied.');
        } catch (AuthenticationDenied $exception) {
            self::assertNotEmpty($exception->getMessage());
        }
    }
}
