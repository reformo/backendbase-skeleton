<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services\Sessions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedRedis;

final class RedisRenewalTest extends TestCase
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

    public function testActivationAndTouchUseSeparateDeadlinesWithoutExtendingTheAccessToken(): void
    {
        $fixture   = $this->fixture;
        $client    = $fixture->client();
        $json      = $fixture->json();
        $registry  = new RedisTokenRegistry($json);
        $now       = new DateTimeImmutable();
        $created   = $now->getTimestamp() - 100;
        $policy    = new SessionPolicy(60, 120, 600);
        $refresh   = RefreshToken::create($this->principal);
        $state     = new SessionState($this->principal, $policy, $created, $created, $created + 600, $refresh->digest());
        $expiresAt = $now->modify('+60 seconds');
        $registry->activate($this->principal, $expiresAt, $state);
        [$token, $session] = $fixture->keys($this->principal);
        self::assertSame($expiresAt->getTimestamp(), $client->rawCommand('EXPIRETIME', $token));
        self::assertSame($created + 120, $client->rawCommand('EXPIRETIME', $session));
        $registry->assertActive($this->principal, true);
        self::assertSame($expiresAt->getTimestamp(), $client->rawCommand('EXPIRETIME', $token));
        self::assertGreaterThan($created + 120, $client->rawCommand('EXPIRETIME', $session));
        $record = $json->get($session);
        self::assertIsArray($record);
        self::assertSame($state->refreshHash, $record['refreshHash']);
        self::assertArrayNotHasKey('refreshToken', $record);
    }

    public function testAtomicRotationPreservesInflightTokensAndConsumedTokenReuseRevokesAllTokens(): void
    {
        $fixture   = $this->fixture;
        $registry  = new RedisTokenRegistry($fixture->json());
        $principal = $this->principal;
        $now       = new DateTimeImmutable();
        $refresh   = RefreshToken::create($principal);
        $session   = Session::start($principal, new SessionPolicy(60, 120, 600), $now, $refresh);
        $state     = $session->snapshot();
        $expiresAt = $session->tokenExpiresAt($now);
        $registry->activate($principal, $expiresAt, $state);
        self::denied(static fn () => $registry->session(Realm::ADMIN, $refresh));
        self::denied(static fn () => $registry->session(Realm::USER, RefreshToken::create($principal)));
        $replacement = RefreshToken::create($principal);
        $session->rotate($replacement, $now);
        $next = $session->snapshot();
        $registry->rotate($refresh, $next, $expiresAt);
        $registry->assertActive($principal);
        $registry->assertActive($next->principal);
        $active = $registry->session(Realm::USER, $replacement);
        self::assertSame($state->maximumExpiresAt, $active->maximumExpiresAt);
        $replay = $registry->session(Realm::USER, $refresh, true);
        self::assertSame($active->refreshHash, $replay->refreshHash);
        self::denied(static fn () => $registry->session(Realm::USER, $refresh));
        self::denied(static fn () => $registry->assertActive($principal));
        self::denied(static fn () => $registry->assertActive($next->principal));
        self::denied(static fn () => $registry->session(Realm::USER, $replacement));
    }

    public function testRotationRaceCannotActivateTwoDescendants(): void
    {
        $fixture   = $this->fixture;
        $registry  = new RedisTokenRegistry($fixture->json());
        $principal = $this->principal;
        $now       = new DateTimeImmutable();
        $refresh   = RefreshToken::create($principal);
        $session   = Session::start($principal, new SessionPolicy(60, 120, 600), $now, $refresh);
        $state     = $session->snapshot();
        $expires   = $session->tokenExpiresAt($now);
        $registry->activate($principal, $expires, $state);
        $first  = new Session($state);
        $second = new Session($state);
        $first->rotate(RefreshToken::create($principal), $now);
        $second->rotate(RefreshToken::create($principal), $now);
        $winner = $first->snapshot();
        $loser  = $second->snapshot();
        $registry->rotate($refresh, $winner, $expires);
        self::denied(static fn () => $registry->rotate($refresh, $loser, $expires));
        $client                    = $fixture->client();
        [$loserToken, $sessionKey] = $fixture->keys($loser->principal);
        self::assertSame(0, $client->exists($loserToken, $sessionKey));
        self::denied(static fn () => $registry->assertActive($winner->principal));
    }

    private static function denied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Authentication must fail.');
        } catch (AuthenticationDenied $exception) {
            self::assertNotEmpty($exception->getMessage());
        }
    }
}
