<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Application\Sessions\SessionLifecycle;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionRaceTest extends TestCase
{
    public function testAccountClosureDuringRotationRevokesTheReplacementSession(): void
    {
        $fixture         = IdentityFixture::create();
        $enrolled        = IdentityScenario::verified($fixture);
        $container       = $fixture->container();
        $sessions        = $container->get(SessionLifecycle::class);
        $session         = $sessions->start(IdentityScenario::principal($fixture));
        $refresh         = new RefreshToken($session['refreshToken']);
        $currentRegistry = $container->get(TokenRegistry::class);
        $state           = $currentRegistry->session(Realm::USER, $refresh);
        $store           = $container->get(IdentityStore::class);
        $registry        = $this->createMock(TokenRegistry::class);
        $registry->expects(self::once())->method('session')->willReturn($state);
        $registry->expects(self::once())->method('rotate')->willReturnCallback(static function () use ($store, $enrolled): void {
            $store->transaction(static function () use ($store, $enrolled): void {
                $account = $store->accountForUpdate($enrolled['accountId']);
                $account->close(new DateTimeImmutable('2026-10-14T10:02:00Z'));
                $store->saveAccount($account);
            });
        });
        $registry->expects(self::once())->method('revokeSession');
        $tokens   = new AuthenticationTokens($container->get(TokenCodec::class), $registry);
        $issuance = new SessionIssuance($tokens, new AuthenticationGate($store, $tokens));
        $clock    = $container->get(Clock::class);
        $this->expectException(AuthenticationDenied::class);
        $issuance->renew(Realm::USER, $refresh, $clock->now());
    }

    public function testAccountClosureDuringRedisActivationRevokesTheNewSession(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::verified($fixture);
        $principal = IdentityScenario::principal($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $registry  = $this->createMock(TokenRegistry::class);
        $registry->expects(self::once())->method('activate')->willReturnCallback(static function () use ($store, $enrolled): void {
            $store->transaction(static function () use ($store, $enrolled): void {
                $account = $store->accountForUpdate($enrolled['accountId']);
                $account->close(new DateTimeImmutable('2026-10-14T10:01:00Z'));
                $store->saveAccount($account);
            });
        });
        $registry->expects(self::once())->method('revokeSession')->with($principal);
        $tokens   = new AuthenticationTokens($container->get(TokenCodec::class), $registry);
        $issuance = new SessionIssuance($tokens, new AuthenticationGate($store, $tokens));
        $this->expectException(AuthenticationDenied::class);
        $issuance->issue($principal, new DateTimeImmutable('2026-10-14T10:15:00Z'));
    }
}
