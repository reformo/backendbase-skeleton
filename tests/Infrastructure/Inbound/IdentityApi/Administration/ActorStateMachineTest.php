<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Infrastructure\Inbound\IdentityApi\AdminContactVerificationTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ActorStateMachineTest extends TestCase
{
    public function testAnExistingSessionIsDeniedDuringRemovalAndAcceptedAfterRestoration(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $target     = IdentityScenario::verified($fixture, '+905551234569', 'target@example.com');
        $adminApi   = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $userApi    = IdentityApiFixture::create($fixture, Realm::USER);
        $adminToken = AdminContactVerificationTest::token($fixture, Realm::ADMIN);
        $login      = $userApi->request('POST', '/auth/login', ['identifier' => 'target@example.com', 'password' => 'valid-password-123']);
        self::assertSame(200, $login->getStatusCode());
        $session   = UserFlowTest::resource($login, 'session');
        $userToken = UserFlowTest::text($session, 'accessToken');
        self::assertSame(200, self::responseStatusCode($userApi->request('GET', '/identity', token: $userToken)));
        $change = ['actorId' => $target['actorId'], 'realm' => 'USER', 'action' => 'remove'];
        self::assertSame(200, self::responseStatusCode($adminApi->request('POST', '/administration/actors/change', $change, $adminToken, 'remove-first')));
        self::assertSame(200, self::responseStatusCode($adminApi->request('POST', '/administration/actors/change', $change, $adminToken, 'remove-again')));
        self::assertSame(401, self::responseStatusCode($userApi->request('GET', '/identity', token: $userToken)));
        $change['action'] = 'restore';
        self::assertSame(200, self::responseStatusCode($adminApi->request('POST', '/administration/actors/change', $change, $adminToken, 'restore-first')));
        self::assertSame(200, self::responseStatusCode($adminApi->request('POST', '/administration/actors/change', $change, $adminToken, 'restore-again')));
        self::assertSame(200, self::responseStatusCode($userApi->request('GET', '/identity', token: $userToken)));
    }

    public function testARemovedActorCannotBeRestoredAfterItsAccountCloses(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $target     = IdentityScenario::admin($fixture, '+905551234569', 'target@example.com');
        $api        = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $adminToken = AdminContactVerificationTest::token($fixture, Realm::ADMIN);
        $login      = $api->request('POST', '/auth/login', ['identifier' => 'target@example.com', 'password' => 'valid-password-123']);
        self::assertSame(200, $login->getStatusCode());
        $session     = UserFlowTest::resource($login, 'session');
        $targetToken = UserFlowTest::text($session, 'accessToken');
        $change      = ['actorId' => $target['userActorId'], 'realm' => 'USER', 'action' => 'remove'];
        self::assertSame(200, self::responseStatusCode($api->request('POST', '/administration/actors/change', $change, $adminToken)));
        self::assertSame(200, self::responseStatusCode($api->request('DELETE', '/identity/account', token: $targetToken)));
        $change['action'] = 'restore';
        self::assertSame(401, self::responseStatusCode($api->request('POST', '/administration/actors/change', $change, $adminToken)));
        $connection = $fixture->connection();
        $deletedAt  = $connection->fetchOne('SELECT deleted_at FROM `user` WHERE uuid = ?', [$target['userActorId']]);
        self::assertIsString($deletedAt);
    }

    public function testApiReapprovalReplacesEvidenceWithoutRemovingApproval(): void
    {
        $fixture = IdentityFixture::create();
        $admin   = IdentityScenario::admin($fixture);
        $target  = IdentityScenario::verified($fixture, '+905551234569', 'target@example.com');
        $api     = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $token   = AdminContactVerificationTest::token($fixture, Realm::ADMIN);
        $invite  = $api->request('POST', '/administration/admins/invite', ['accountId' => $target['accountId'], 'evidence' => 'ticket:invite'], $token);
        self::assertSame(201, $invite->getStatusCode());
        $invitation = UserFlowTest::resource($invite, 'invitation');
        $actorId    = UserFlowTest::text($invitation, 'actorId');
        $approval   = ['actorId' => $actorId, 'evidence' => 'ticket:approval-one'];
        self::assertSame(200, self::responseStatusCode($api->request('POST', '/administration/admins/approve', $approval, $token)));
        $approval['evidence'] = 'ticket:approval-two';
        self::assertSame(200, self::responseStatusCode($api->request('POST', '/administration/admins/approve', $approval, $token)));
        $connection = $fixture->connection();
        $stored     = $connection->fetchOne('SELECT state FROM `admin` WHERE uuid = ?', [$actorId]);
        self::assertIsString($stored);
        $state = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($state);
        self::assertTrue($state['approved']);
        self::assertSame('approve:' . $admin['actorId'] . ':ticket:approval-two', $state['approval']);
        $approval['evidence'] = 'ticket:invite';
        self::assertSame(400, self::responseStatusCode($api->request('POST', '/administration/admins/approve', $approval, $token)));
        self::assertSame($stored, $connection->fetchOne('SELECT state FROM `admin` WHERE uuid = ?', [$actorId]));
    }

    private static function responseStatusCode(ResponseInterface $response): int
    {
        return $response->getStatusCode();
    }
}
