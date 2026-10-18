<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\Idempotency;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\IdentityApi\ContactVerificationFlowTest;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class ContactReplayTest extends TestCase
{
    public function testRequestAndResendRetriesDoNotSendAnotherMessage(): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $context = ContactScenario::token();
        $payload = ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => $context];
        $first   = $api->request('POST', '/auth/contact-verifications', $payload, idempotencyKey: 'request-contact');
        $retry   = $api->request('POST', '/auth/contact-verifications', $payload, idempotencyKey: 'request-contact');
        self::assertSame(200, $retry->getStatusCode());
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $retry->getBody());
        $contact   = UserFlowTest::resource($first, 'contactInfo');
        $id        = UserFlowTest::text($contact, 'contactInfoId');
        $container = $fixture->container();
        $notifier  = $container->get(ContactNotifier::class);
        self::assertInstanceOf(CapturedContactNotifier::class, $notifier);
        self::assertCount(1, $notifier->deliveries());
        IdentityScenario::advance($fixture, 'PT61S');
        $path  = '/auth/contact-verifications/' . $id . '/resend';
        $body  = ['verificationContextToken' => $context];
        $first = $api->request('POST', $path, $body, idempotencyKey: 'resend-contact');
        $retry = $api->request('POST', $path, $body, idempotencyKey: 'resend-contact');
        self::assertSame(200, $first->getStatusCode());
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $retry->getBody());
        self::assertCount(2, $notifier->deliveries());
        $code  = ContactVerificationFlowTest::code($fixture);
        $proof = ['verificationContextToken' => $context, 'code' => $code];
        $path  = '/auth/contact-verifications/' . $id . '/confirm';
        $first = $api->request('POST', $path, $proof, idempotencyKey: 'confirm-contact');
        IdentityScenario::advance($fixture, 'PT6M');
        $retry = $api->request('POST', $path, $proof, idempotencyKey: 'confirm-contact');
        self::assertSame(200, $first->getStatusCode());
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $retry->getBody());
    }

    public function testRegistrationRetryReturnsTheOriginalCreatedIdentity(): void
    {
        $fixture = IdentityFixture::create();
        $payload = ContactScenario::verified($fixture);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $first   = $api->request('POST', '/auth/register', $payload, idempotencyKey: 'one-registration');
        $retry   = $api->request('POST', '/auth/register', $payload, idempotencyKey: 'one-registration');
        self::assertSame(201, $first->getStatusCode());
        self::assertSame(201, $retry->getStatusCode());
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $retry->getBody());
        $connection = $fixture->connection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM account'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM user'));
    }
}
