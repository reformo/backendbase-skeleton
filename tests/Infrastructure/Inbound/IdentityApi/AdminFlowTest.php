<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminFlowTest extends TestCase
{
    public function testAdminCannotApproveItsOwnEnrollment(): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, $admin['secret'], Realm::ADMIN, 'ada.admin');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        self::assertSame(403, $api->request('POST', '/administration/admins/approve', ['actorId' => $admin['actorId'], 'evidence' => 'ticket:self-approval'], $token)->getStatusCode());
    }

    #[Test]
    public function itSeparatesInvitationApprovalAndRealmAuthentication(): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $target    = IdentityScenario::enrolled($fixture, 'target.user', 'target@example.com');
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, $admin['secret'], Realm::ADMIN, 'ada.admin');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $response  = $api->request('POST', '/administration/admins/invite', ['accountId' => $target['accountId'], 'loginName' => 'target.user', 'evidence' => 'ticket:invite-1'], $token);
        self::assertSame(201, $response->getStatusCode());
        $invite = UserFlowTest::data($response);
        self::assertFalse($invite['approved']);
        $targetAdminId = UserFlowTest::text($invite, 'actorId');
        $response      = $api->request('POST', '/auth/login', ['loginName' => 'target.user', 'password' => 'valid-password-123']);
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('POST', '/administration/admins/approve', ['actorId' => $targetAdminId, 'evidence' => 'ticket:approval-1'], $token);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('POST', '/auth/login', ['loginName' => 'target.user', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $begin    = UserFlowTest::data($response);
        $response = $api->request('POST', '/auth/login/complete', ['challengeId' => UserFlowTest::text($begin, 'challengeId'), 'code' => IdentityScenario::code($fixture, $target['secret'])]);
        self::assertSame(200, $response->getStatusCode());
        $session     = UserFlowTest::data($response);
        $targetToken = UserFlowTest::text($session, 'accessToken');
        self::assertSame(200, $api->request('GET', '/identity', token: $targetToken)->getStatusCode());
        $userApi = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(401, $userApi->request('GET', '/identity', token: $targetToken)->getStatusCode());
        IdentityScenario::advance($fixture, 'PT31S');
        $userPrincipal = IdentityScenario::principal($fixture, $target['secret'], Realm::USER, 'target.user');
        $userToken     = $issuer->issue($userPrincipal, $clock->sessionExpiresAt());
        self::assertSame(401, $api->request('GET', '/identity', token: $userToken)->getStatusCode());
        self::assertSame(200, $api->request('DELETE', '/identity/session', token: $targetToken)->getStatusCode());
        self::assertSame(401, $api->request('GET', '/identity', token: $targetToken)->getStatusCode());
    }

    #[Test]
    public function itPreservesActorHistoryAfterRemovalAndChecksRestoration(): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $target    = IdentityScenario::enrolled($fixture, 'target.user', 'target@example.com');
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, $admin['secret'], Realm::ADMIN, 'ada.admin');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $response  = $api->request('POST', '/administration/actors/change', ['actorId' => $target['actorId'], 'realm' => 'USER', 'action' => 'remove'], $token);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('GET', '/administration/actors/USER/' . $target['actorId'], token: $token);
        self::assertSame(200, $response->getStatusCode());
        $history = UserFlowTest::data($response);
        self::assertSame('target.user', $history['loginName']);
        self::assertIsString($history['createdAt']);
        self::assertIsString($history['deletedAt']);
        self::assertSame(200, $api->request('POST', '/administration/actors/change', ['actorId' => $target['actorId'], 'realm' => 'USER', 'action' => 'restore'], $token)->getStatusCode());
        $response = $api->request('GET', '/administration/actors/USER/' . $target['actorId'], token: $token);
        $history  = UserFlowTest::data($response);
        self::assertNull($history['deletedAt']);
    }
}
