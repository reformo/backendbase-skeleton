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
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'admin@example.com');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        self::assertSame(403, $api->request('POST', '/administration/admins/approve', ['actorId' => $admin['actorId'], 'evidence' => 'ticket:self-approval'], $token)->getStatusCode());
    }

    #[Test]
    public function itSeparatesInvitationApprovalAndRealmAuthentication(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $target    = IdentityScenario::verified($fixture, '+905551234569', 'target@example.com');
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'admin@example.com');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $response  = $api->request('POST', '/administration/admins/invite', ['accountId' => $target['accountId'], 'evidence' => 'ticket:invite-1'], $token);
        self::assertSame(201, $response->getStatusCode());
        $invite = UserFlowTest::resource($response, 'invitation');
        self::assertFalse($invite['approved']);
        $targetAdminId = UserFlowTest::text($invite, 'actorId');
        $response      = $api->request('POST', '/auth/login', ['identifier' => 'target@example.com', 'password' => 'valid-password-123']);
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('POST', '/administration/admins/approve', ['actorId' => $targetAdminId, 'evidence' => 'ticket:approval-1'], $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], UserFlowTest::resource($response, 'admin'));
        $response = $api->request('POST', '/auth/login', ['identifier' => 'target@example.com', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $session     = UserFlowTest::resource($response, 'session');
        $targetToken = UserFlowTest::text($session, 'accessToken');
        $response    = $api->request('GET', '/identity', token: $targetToken);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ADMIN', UserFlowTest::resource($response, 'admin')['realm']);
        $userApi = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(401, $userApi->request('GET', '/identity', token: $targetToken)->getStatusCode());
        $userPrincipal = IdentityScenario::principal($fixture, Realm::USER, 'target@example.com');
        $userToken     = $issuer->issue($userPrincipal, $clock->sessionExpiresAt());
        self::assertSame(401, $api->request('GET', '/identity', token: $userToken)->getStatusCode());
        $response = $api->request('DELETE', '/identity/session', token: $targetToken);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], UserFlowTest::resource($response, 'session'));
        self::assertSame(401, $api->request('GET', '/identity', token: $targetToken)->getStatusCode());
    }

    #[Test]
    public function itPreservesActorHistoryAfterRemovalAndChecksRestoration(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $target    = IdentityScenario::verified($fixture, '+905551234569', 'target@example.com');
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'admin@example.com');
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $response  = $api->request('POST', '/administration/actors/change', ['actorId' => $target['actorId'], 'realm' => 'USER', 'action' => 'remove'], $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], UserFlowTest::resource($response, 'actor'));
        $response = $api->request('GET', '/administration/actors/USER/' . $target['actorId'], token: $token);
        self::assertSame(200, $response->getStatusCode());
        $history = UserFlowTest::resource($response, 'actor');
        self::assertSame('target@example.com', $history['email']);
        self::assertSame('+905551234569', $history['mobile']);
        self::assertArrayNotHasKey('loginName', $history);
        self::assertIsString($history['createdAt']);
        self::assertIsString($history['deletedAt']);
        self::assertSame(200, $api->request('POST', '/administration/actors/change', ['actorId' => $target['actorId'], 'realm' => 'USER', 'action' => 'restore'], $token)->getStatusCode());
        $response = $api->request('GET', '/administration/actors/USER/' . $target['actorId'], token: $token);
        $history  = UserFlowTest::resource($response, 'actor');
        self::assertNull($history['deletedAt']);
    }
}
