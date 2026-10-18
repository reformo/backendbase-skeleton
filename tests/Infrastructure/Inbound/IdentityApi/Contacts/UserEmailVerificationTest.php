<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\ContactVerificationFlowTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class UserEmailVerificationTest extends TestCase
{
    #[DataProvider('apis')]
    public function testRegistrationNeedsOnlyMobileProofAndEmailCanBeVerifiedAfterLogin(bool $example): void
    {
        $fixture = ExampleApiFixture::identity();
        $api     = $example ? IdentityApiFixture::example($fixture) : IdentityApiFixture::create($fixture, Realm::USER);
        $context = ContactScenario::token();
        ContactVerificationFlowTest::confirm($fixture, $api, 'mobile', '+905551234567', $context);
        $registration = ['email' => 'ADA@EXAMPLE.COM', 'mobile' => '+905551234567', 'password' => 'valid-password-123', 'verificationContextToken' => $context];
        self::assertSame(201, self::responseStatus($api->request('POST', '/auth/register', $registration)));
        $token      = self::login($api);
        $connection = $fixture->connection();
        $before     = $connection->fetchOne('SELECT state FROM account');
        $context    = ContactScenario::token();
        $payload    = ['contactType' => 'email', 'verificationContextToken' => $context];
        $response   = $api->request('POST', '/identity/contact-verifications', $payload, $token);
        self::assertSame(200, $response->getStatusCode());
        $contact = UserFlowTest::resource($response, 'contactInfo');
        self::assertNull($contact['verifiedAt']);
        self::assertNull($contact['registrationValidUntil']);
        $id   = UserFlowTest::text($contact, 'contactInfoId');
        $path = '/identity/contact-verifications/' . $id;
        $old  = ContactVerificationFlowTest::code($fixture);
        self::assertSame('ada@example.com', $connection->fetchOne("SELECT normalized_value FROM contact_verification WHERE purpose = 'user-contact-verification'"));
        self::assertSame(429, self::responseStatus($api->request('POST', $path . '/resend', ['verificationContextToken' => $context], $token)));
        IdentityScenario::advance($fixture, 'PT60S');
        self::assertSame(200, self::responseStatus($api->request('POST', $path . '/resend', ['verificationContextToken' => $context], $token)));
        $code = ContactVerificationFlowTest::code($fixture);
        self::assertSame(401, self::responseStatus($api->request('POST', $path . '/confirm', ['verificationContextToken' => $context, 'code' => $old], $token)));
        $proof    = ['verificationContextToken' => $context, 'code' => $code];
        $response = $api->request('POST', $path . '/confirm', $proof, $token);
        self::assertSame(200, $response->getStatusCode());
        $verified = UserFlowTest::resource($response, 'contactInfo');
        self::assertNotNull($verified['verifiedAt']);
        self::assertNull($verified['registrationValidUntil']);
        self::assertSame($verified, UserFlowTest::resource($api->request('POST', $path . '/confirm', $proof, $token), 'contactInfo'));
        self::assertSame(409, self::responseStatus($api->request('POST', $path . '/resend', ['verificationContextToken' => $context], $token)));
        self::assertSame($before, $connection->fetchOne('SELECT state FROM account'));
        self::assertSame(200, self::responseStatus($api->request('GET', '/identity', token: $token)));
        self::assertSame(1, $connection->fetchOne("SELECT COUNT(*) FROM contact_verification WHERE purpose = 'user-contact-verification' AND verified_at IS NOT NULL AND account_id IS NOT NULL AND actor_id IS NOT NULL"));
        self::assertSame(401, self::responseStatus($api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', $proof)));
    }

    /** @return iterable<string, array{bool}> */
    public static function apis(): iterable
    {
        yield 'UserApi' => [false];
        yield 'ExampleApi' => [true];
    }

    public static function responseStatus(ResponseInterface $response): int
    {
        return $response->getStatusCode();
    }

    public static function login(IdentityApiFixture $api, string $identifier = 'ada@example.com'): string
    {
        $response = $api->request('POST', '/auth/login', ['identifier' => $identifier, 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $session = UserFlowTest::resource($response, 'session');

        return UserFlowTest::text($session, 'accessToken');
    }
}
