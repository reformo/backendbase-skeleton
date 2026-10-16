<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;

use function count;

final class AdminContactVerificationTest extends TestCase
{
    public function testOptionalVerificationPreservesBothRealmSessionsAndAccountOwnership(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $adminApi   = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $userApi    = IdentityApiFixture::create($fixture, Realm::USER);
        $adminToken = self::token($fixture, Realm::ADMIN);
        $userToken  = self::token($fixture, Realm::USER);
        $connection = $fixture->connection();
        $before     = $connection->fetchOne('SELECT state FROM identity_account');
        $context    = ContactScenario::token();
        IdentityScenario::advance($fixture, 'PT60S');
        $response = $adminApi->request('POST', '/identity/contact-verifications', ['contactType' => 'mobile', 'verificationContextToken' => $context], $adminToken);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        self::resend($fixture, $adminApi, '/identity/contact-verifications/' . $id, $context, $adminToken);
        $valid = ContactVerificationFlowTest::code($fixture);
        $wrong = $valid === '000000' ? '111111' : '000000';
        $path  = '/identity/contact-verifications/' . $id . '/confirm';
        self::assertSame(401, $adminApi->request('POST', $path, ['verificationContextToken' => $context, 'code' => $wrong], $adminToken)->getStatusCode());
        self::assertSame(200, $adminApi->request('POST', $path, ['verificationContextToken' => $context, 'code' => $valid], $adminToken)->getStatusCode());
        self::assertSame($before, $connection->fetchOne('SELECT state FROM identity_account'));
        self::assertSame(200, $adminApi->request('GET', '/identity', token: $adminToken)->getStatusCode());
        self::assertSame(200, $userApi->request('GET', '/identity', token: $userToken)->getStatusCode());
        self::assertSame(401, $adminApi->request('POST', '/identity/contact-verifications', ['contactType' => 'email', 'verificationContextToken' => $context], $userToken)->getStatusCode());
    }

    public function testPendingAdminCanVerifyStoredContactsWithoutGainingApprovalOrASession(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE identity_admin SET state = json_set(state, '$.approved', json('false'))");
        IdentityScenario::advance($fixture, 'PT60S');
        $api     = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $context = ContactScenario::token();
        $request = ['identifier' => 'admin@example.com', 'password' => 'valid-password-123', 'contactType' => 'email', 'verificationContextToken' => $context];
        self::assertSame(401, $api->request('POST', '/auth/contact-verifications', [...$request, 'password' => 'wrong-password-123'])->getStatusCode());
        self::assertSame(400, $api->request('POST', '/auth/contact-verifications', [...$request, 'contactValue' => 'attacker@example.com'])->getStatusCode());
        $response = $api->request('POST', '/auth/contact-verifications', $request);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        self::resend($fixture, $api, '/auth/contact-verifications/' . $id, $context);
        $code = ContactVerificationFlowTest::code($fixture);
        self::assertSame(200, $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $context, 'code' => $code])->getStatusCode());
        self::assertSame(0, $connection->fetchOne("SELECT json_extract(state, '$.approved') FROM identity_admin"));
        self::assertSame(401, $api->request('POST', '/auth/login', ['identifier' => 'admin@example.com', 'password' => 'valid-password-123'])->getStatusCode());
        $userApi = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(401, $userApi->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $context, 'code' => $code])->getStatusCode());
    }

    public function testInvalidAdminRequestsAndRemovedActorsCannotVerifyContacts(): void
    {
        $fixture = IdentityFixture::create();
        $admin   = IdentityScenario::admin($fixture);
        $api     = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $token   = ContactScenario::token();
        $request = ['identifier' => 'unknown@example.com', 'password' => 'valid-password-123', 'contactType' => 'email', 'verificationContextToken' => $token];
        self::assertSame(401, $api->request('POST', '/auth/contact-verifications', $request)->getStatusCode());
        $request['identifier'] = 'admin@example.com';
        self::assertSame(400, $api->request('POST', '/auth/contact-verifications', [...$request, 'contactType' => 'fax'])->getStatusCode());
        IdentityScenario::advance($fixture, 'PT60S');
        $response   = $api->request('POST', '/auth/contact-verifications', $request);
        $contact    = UserFlowTest::resource($response, 'contactInfo');
        $id         = UserFlowTest::text($contact, 'contactInfoId');
        $code       = ContactVerificationFlowTest::code($fixture);
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE identity_admin SET deleted_at = '2026-10-14 10:02:00.000000' WHERE uuid = ?", [$admin['actorId']]);
        self::assertSame(401, $api->request('POST', '/auth/contact-verifications', $request)->getStatusCode());
        self::assertSame(401, $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $token, 'code' => $code])->getStatusCode());
    }

    private static function resend(IdentityFixture $fixture, IdentityApiFixture $api, string $path, string $context, string|null $token = null): void
    {
        $container = $fixture->container();
        $notifier  = $container->get(ContactNotifier::class);
        self::assertInstanceOf(CapturedContactNotifier::class, $notifier);
        $deliveries = $notifier->deliveries();
        $payload    = ['verificationContextToken' => $context];
        $response   = $api->request('POST', $path . '/resend', $payload, $token);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->getHeaderLine('Retry-After'));
        IdentityScenario::advance($fixture, 'PT60S');
        $response = $api->request('POST', $path . '/resend', ['verificationContextToken' => ContactScenario::token()], $token);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame($deliveries, $notifier->deliveries());
        $response = $api->request('POST', $path . '/resend', $payload, $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('accepted', UserFlowTest::resource($response, 'contactInfo')['deliveryState']);
        self::assertCount(count($deliveries) + 1, $notifier->deliveries());
    }

    public static function token(IdentityFixture $fixture, Realm $realm): string
    {
        $container = $fixture->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($fixture, $realm, 'admin@example.com');

        return $issuer->issue($principal, $clock->sessionExpiresAt());
    }
}
