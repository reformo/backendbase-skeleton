<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionRaceTest extends TestCase
{
    public function testAccountClosureDuringRedisActivationRevokesTheNewSession(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::enrolled($fixture);
        $principal = IdentityScenario::principal($fixture, $enrolled['secret']);
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
