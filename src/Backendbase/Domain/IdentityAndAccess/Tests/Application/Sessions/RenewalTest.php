<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\Sessions\SessionLifecycle;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RealmSessionPolicies;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;

final class RenewalTest extends TestCase
{
    public function testExpiredAccessTokenCanBeRenewedWithoutChangingTheSessionDeadline(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::verified($fixture);
        $container = $fixture->container();
        $sessions  = $container->get(SessionLifecycle::class);
        $gate      = $container->get(AuthenticationGate::class);
        $session   = $sessions->start(IdentityScenario::principal($fixture));
        $before    = $gate->verify(Realm::USER, $session['accessToken']);
        IdentityScenario::advance($fixture, 'PT900S');
        self::denied(static fn () => $gate->verify(Realm::USER, $session['accessToken']));
        $renewed        = $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken']));
        $after          = $gate->verify(Realm::USER, $renewed['accessToken']);
        $beforeIdentity = $before->identity();
        $afterIdentity  = $after->identity();
        self::assertSame($beforeIdentity['sid'], $afterIdentity['sid']);
        self::assertNotSame($beforeIdentity['jti'], $afterIdentity['jti']);
        self::assertNotSame($session['refreshToken'], $renewed['refreshToken']);
        self::assertSame($session['sessionExpiresAt'], $renewed['sessionExpiresAt']);
        self::assertGreaterThan($session['sessionIdleExpiresAt'], $renewed['sessionIdleExpiresAt']);
    }

    public function testAuthenticatedActivityExtendsIdleButCannotExtendTheMaximum(): void
    {
        $policy  = new SessionPolicy(60, 120, 180);
        $fixture = IdentityFixture::create([RealmSessionPolicies::class => new RealmSessionPolicies($policy, $policy)]);
        IdentityScenario::verified($fixture);
        $container = $fixture->container();
        $sessions  = $container->get(SessionLifecycle::class);
        $gate      = $container->get(AuthenticationGate::class);
        $session   = $sessions->start(IdentityScenario::principal($fixture));
        IdentityScenario::advance($fixture, 'PT50S');
        $gate->verify(Realm::USER, $session['accessToken']);
        IdentityScenario::advance($fixture, 'PT80S');
        $session = $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken']));
        self::assertSame($session['sessionExpiresAt'], $session['expiresAt']);
        IdentityScenario::advance($fixture, 'PT50S');
        self::denied(static fn () => $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken'])));
    }

    public function testIdleBoundaryRejectsRenewalAndInvalidDatabaseStateCannotExtendIdle(): void
    {
        $policy     = new SessionPolicy(300, 120, 600);
        $fixture    = IdentityFixture::create([RealmSessionPolicies::class => new RealmSessionPolicies($policy, $policy)]);
        $account    = IdentityScenario::verified($fixture);
        $container  = $fixture->container();
        $sessions   = $container->get(SessionLifecycle::class);
        $gate       = $container->get(AuthenticationGate::class);
        $session    = $sessions->start(IdentityScenario::principal($fixture));
        $connection = $fixture->connection();
        IdentityScenario::advance($fixture, 'PT100S');
        $connection->executeStatement("UPDATE `user` SET deleted_at = '2026-10-14 10:02:00' WHERE uuid = ?", [$account['actorId']]);
        self::denied(static fn () => $gate->verify(Realm::USER, $session['accessToken']));
        $connection->executeStatement('UPDATE `user` SET deleted_at = NULL WHERE uuid = ?', [$account['actorId']]);
        IdentityScenario::advance($fixture, 'PT20S');
        self::denied(static fn () => $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken'])));
    }

    public function testUnknownSecretAndWrongRealmDoNotRevokeButConsumedSecretRevokesEveryAccessToken(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::verified($fixture);
        $container = $fixture->container();
        $sessions  = $container->get(SessionLifecycle::class);
        $gate      = $container->get(AuthenticationGate::class);
        $principal = IdentityScenario::principal($fixture);
        $session   = $sessions->start($principal);
        self::denied(static fn () => $sessions->renew(Realm::USER, RefreshToken::create($principal)));
        self::denied(static fn () => $sessions->renew(Realm::ADMIN, new RefreshToken($session['refreshToken'])));
        $renewed = $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken']));
        $gate->verify(Realm::USER, $session['accessToken']);
        $gate->verify(Realm::USER, $renewed['accessToken']);
        self::denied(static fn () => $sessions->renew(Realm::USER, new RefreshToken($session['refreshToken'])));
        self::denied(static fn () => $gate->verify(Realm::USER, $session['accessToken']));
        self::denied(static fn () => $gate->verify(Realm::USER, $renewed['accessToken']));
        self::denied(static fn () => $sessions->renew(Realm::USER, new RefreshToken($renewed['refreshToken'])));
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
