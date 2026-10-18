<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\IdentityApi\ContactVerificationFlowTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class UserEmailVerificationFailureTest extends TestCase
{
    public function testVerificationUsesOnlyTheAuthenticatedAccountsStoredEmail(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        IdentityScenario::registered($fixture, '+905551234568', 'other@example.com');
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $token   = UserEmailVerificationTest::login($api);
        $other   = UserEmailVerificationTest::login($api, 'other@example.com');
        $context = ContactScenario::token();
        $payload = ['contactType' => 'email', 'verificationContextToken' => $context];
        self::assertSame(400, UserEmailVerificationTest::responseStatus($api->request('POST', '/identity/contact-verifications', [...$payload, 'contactValue' => 'attacker@example.com'], $token)));
        self::assertSame(400, UserEmailVerificationTest::responseStatus($api->request('POST', '/identity/contact-verifications', [...$payload, 'contactType' => 'mobile'], $token)));
        IdentityScenario::advance($fixture, 'PT60S');
        $response = $api->request('POST', '/identity/contact-verifications', $payload, $token);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        $path    = '/identity/contact-verifications/' . $id;
        $code    = ContactVerificationFlowTest::code($fixture);
        $proof   = ['verificationContextToken' => $context, 'code' => $code];
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/confirm', $proof, $other)));
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/resend', ['verificationContextToken' => $context], $other)));
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', '/identity/contact-verifications', $payload, $other)));
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/confirm', [...$proof, 'verificationContextToken' => ContactScenario::token()], $token)));
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/resend', ['verificationContextToken' => ContactScenario::token()], $token)));
        IdentityScenario::advance($fixture, 'PT5M');
        self::assertSame(410, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/confirm', $proof, $token)));
        $connection = $fixture->connection();
        self::assertSame(0, $connection->fetchOne("SELECT COUNT(*) FROM contact_verification WHERE purpose = 'user-contact-verification' AND verified_at IS NOT NULL"));
        self::assertSame(200, UserEmailVerificationTest::responseStatus($api->request('DELETE', '/identity/account', token: $token)));
        self::assertSame(401, UserEmailVerificationTest::responseStatus($api->request('POST', $path . '/confirm', $proof, $token)));
    }
}
