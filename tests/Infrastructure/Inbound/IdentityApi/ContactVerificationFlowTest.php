<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ContactVerificationFlowTest extends TestCase
{
    public function testEmailAndSmsVerificationPermitRegistrationThroughThePublicApi(): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $token   = ContactScenario::token();
        self::confirm($fixture, $api, 'email', 'ADA@EXAMPLE.COM', $token);
        self::confirm($fixture, $api, 'mobile', '+905551234567', $token);
        $body = ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123', 'verificationContextToken' => $token];
        self::assertSame(201, $api->request('POST', '/auth/register', $body)->getStatusCode());
        self::assertSame(409, $api->request('POST', '/auth/register', $body)->getStatusCode());
        $login = $api->request('POST', '/auth/login', ['identifier' => '+905551234567', 'password' => 'valid-password-123']);
        self::assertSame(200, $login->getStatusCode());
        $connection = $fixture->connection();
        $rows       = $connection->fetchAllAssociative('SELECT state FROM contact_verification');
        self::assertCount(2, $rows);
        self::assertStringNotContainsString($token, json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function testConfirmationRetryDoesNotRenewEvidenceAndVerifiedRowsRejectResend(): void
    {
        $fixture  = IdentityFixture::create();
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $token    = ContactScenario::token();
        $request  = ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => $token];
        $response = $api->request('POST', '/auth/contact-verifications', $request);
        $contact  = UserFlowTest::resource($response, 'contactInfo');
        $id       = UserFlowTest::text($contact, 'contactInfoId');
        $code     = self::code($fixture);
        $proof    = ['verificationContextToken' => $token, 'code' => $code];
        $first    = $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', $proof);
        self::assertSame(200, $first->getStatusCode());
        $verified = UserFlowTest::resource($first, 'contactInfo');
        IdentityScenario::advance($fixture, 'PT6M');
        $retry = $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', $proof);
        self::assertSame($verified, UserFlowTest::resource($retry, 'contactInfo'));
        self::assertSame(409, $api->request('POST', '/auth/contact-verifications/' . $id . '/resend', ['verificationContextToken' => $token])->getStatusCode());
        $again = $api->request('POST', '/auth/contact-verifications', $request);
        self::assertSame($verified, UserFlowTest::resource($again, 'contactInfo'));
        self::assertSame(409, $api->request('POST', '/auth/contact-verifications', [...$request, 'contactValue' => 'other@example.com'])->getStatusCode());
    }

    public function testResendInvalidatesOldCodeAndKeepsFailedAttempts(): void
    {
        $fixture  = IdentityFixture::create();
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $token    = ContactScenario::token();
        $response = $api->request('POST', '/auth/contact-verifications', ['contactType' => 'mobile', 'contactValue' => '+905551234567', 'verificationContextToken' => $token]);
        $view     = UserFlowTest::resource($response, 'contactInfo');
        $id       = UserFlowTest::text($view, 'contactInfoId');
        $old      = self::code($fixture);
        $url      = '/auth/contact-verifications/' . $id;
        $limited  = $api->request('POST', $url . '/resend', ['verificationContextToken' => $token]);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame('60', $limited->getHeaderLine('Retry-After'));
        IdentityScenario::advance($fixture, 'PT60S');
        self::assertSame(200, $api->request('POST', $url . '/resend', ['verificationContextToken' => $token])->getStatusCode());
        $new = self::code($fixture);
        self::assertSame(401, $api->request('POST', $url . '/confirm', ['verificationContextToken' => $token, 'code' => $old])->getStatusCode());
        self::assertSame(200, $api->request('POST', $url . '/confirm', ['verificationContextToken' => $token, 'code' => $new])->getStatusCode());
        self::assertSame(1, $fixture->connection()->fetchOne("SELECT json_extract(state, '$.failedAttempts') FROM contact_verification"));
    }

    public static function confirm(IdentityFixture $fixture, IdentityApiFixture $api, string $type, string $value, string $token): void
    {
        $response = $api->request('POST', '/auth/contact-verifications', ['contactType' => $type, 'contactValue' => $value, 'verificationContextToken' => $token]);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        self::assertSame(200, $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $token, 'code' => self::code($fixture)])->getStatusCode());
    }

    public static function code(IdentityFixture $fixture): string
    {
        $container = $fixture->container();
        $notifier  = $container->get(ContactNotifier::class);
        self::assertInstanceOf(CapturedContactNotifier::class, $notifier);
        $deliveries = $notifier->deliveries();
        self::assertNotEmpty($deliveries);
        $last = $deliveries[count($deliveries) - 1];

        return $notifier->code($last['generationId']);
    }
}
