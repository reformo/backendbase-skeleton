<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Authentication;

use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\ContactVerificationFlowTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class ContactVerificationTest extends TestCase
{
    public function testExampleContactVerificationPermitsUserRegistrationWithoutABearerToken(): void
    {
        $identity = ExampleApiFixture::identity();
        $api      = IdentityApiFixture::example($identity);
        $context  = ContactScenario::token();
        $email    = self::verify($identity, $api, 'email', 'ada@example.com', $context);
        $mobile   = self::verify($identity, $api, 'mobile', '+905551234567', $context);
        $payload  = ['email' => 'ada@example.com', 'mobile' => '+905551234567', 'password' => 'valid-password-123', 'emailContactInfoId' => $email, 'mobileContactInfoId' => $mobile, 'verificationContextToken' => $context];

        self::assertSame(201, $api->request('POST', '/auth/register', $payload)->getStatusCode());
        self::assertSame(409, $api->request('POST', '/auth/register', $payload)->getStatusCode());
        self::assertSame(200, $api->request('POST', '/auth/login', ['identifier' => '+905551234567', 'password' => 'valid-password-123'])->getStatusCode());
        self::assertSame(401, $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'wrong-password-123'])->getStatusCode());
        self::assertSame(400, $api->request('POST', '/auth/login', ['identifier' => 42, 'password' => 'valid-password-123'])->getStatusCode());
    }

    private static function verify(IdentityFixture $identity, IdentityApiFixture $api, string $type, string $value, string $context): string
    {
        $response = $api->request('POST', '/auth/contact-verifications', ['contactType' => $type, 'contactValue' => $value, 'verificationContextToken' => $context]);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        $id      = UserFlowTest::text($contact, 'contactInfoId');
        $path    = '/auth/contact-verifications/' . $id;
        $payload = ['verificationContextToken' => $context];
        self::assertSame(429, $api->request('POST', $path . '/resend', $payload)->getStatusCode());
        IdentityScenario::advance($identity, 'PT60S');
        self::assertSame(200, $api->request('POST', $path . '/resend', $payload)->getStatusCode());
        $code = ContactVerificationFlowTest::code($identity);
        self::assertSame(200, $api->request('POST', $path . '/confirm', [...$payload, 'code' => $code])->getStatusCode());

        return $id;
    }
}
