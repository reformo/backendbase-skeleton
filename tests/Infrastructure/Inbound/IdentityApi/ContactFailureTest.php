<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function range;

final class ContactFailureTest extends TestCase
{
    public function testUnknownDeliveryIsNotRepeatedAndDoesNotVerifyTheContact(): void
    {
        $notifier = $this->createMock(ContactNotifier::class);
        $notifier->expects(self::exactly(2))->method('send')->willThrowException(new RuntimeException('Unknown provider outcome.'));
        $fixture  = IdentityFixture::create([ContactNotifier::class => $notifier]);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $request  = ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => ContactScenario::token()];
        $response = $api->request('POST', '/auth/contact-verifications', $request);
        self::assertSame(503, $response->getStatusCode());
        $connection = $fixture->connection();
        $id         = $connection->fetchOne('SELECT uuid FROM contact_info');
        self::assertIsString($id);
        self::assertNull($connection->fetchOne('SELECT verified_at FROM contact_info'));
        $retry = $api->request('POST', '/auth/contact-verifications', $request);
        self::assertSame(200, $retry->getStatusCode());
        self::assertSame('unknown', UserFlowTest::resource($retry, 'contactInfo')['deliveryState']);
        $oldGeneration = $connection->fetchOne("SELECT json_extract(state, '$.generationId') FROM contact_info");
        IdentityScenario::advance($fixture, 'PT60S');
        self::assertSame(503, $api->request('POST', '/auth/contact-verifications/' . $id . '/resend', ['verificationContextToken' => $request['verificationContextToken']])->getStatusCode());
        self::assertNotSame($oldGeneration, $connection->fetchOne("SELECT json_extract(state, '$.generationId') FROM contact_info"));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
    }

    public function testUnavailableRequestControlsFailBeforeContactMutationOrDelivery(): void
    {
        $limiter = $this->createStub(VerificationLimiter::class);
        $limiter->method('send')->willThrowException(AuthenticationUnavailable::create('Controls unavailable.'));
        $notifier = $this->createMock(ContactNotifier::class);
        $notifier->expects(self::never())->method('send');
        $fixture = IdentityFixture::create([VerificationLimiter::class => $limiter, ContactNotifier::class => $notifier]);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(503, $api->request('POST', '/auth/contact-verifications', ['contactType' => 'mobile', 'contactValue' => '+905551234567', 'verificationContextToken' => ContactScenario::token()])->getStatusCode());
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM contact_info'));
    }

    public function testFiveFailedConfirmationsPersistAndCannotBeResetByResend(): void
    {
        $fixture  = IdentityFixture::create();
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $token    = ContactScenario::token();
        $response = $api->request('POST', '/auth/contact-verifications', ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => $token]);
        $contact  = UserFlowTest::resource($response, 'contactInfo');
        $id       = UserFlowTest::text($contact, 'contactInfoId');
        $valid    = ContactVerificationFlowTest::code($fixture);
        $wrong    = $valid === '000000' ? '111111' : '000000';
        foreach (range(1, 5) as $attempt) {
            self::assertSame(401, $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $token, 'code' => $wrong])->getStatusCode());
        }

        IdentityScenario::advance($fixture, 'PT60S');
        self::assertSame(429, $api->request('POST', '/auth/contact-verifications/' . $id . '/resend', ['verificationContextToken' => $token])->getStatusCode());
        self::assertSame(429, $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $token, 'code' => $valid])->getStatusCode());
        self::assertSame(5, $fixture->connection()->fetchOne("SELECT json_extract(state, '$.failedAttempts') FROM contact_info"));
    }

    public function testPendingDeliveryIsNotAutomaticallyRepeatedAfterAnInterruptedRequest(): void
    {
        $notifier = $this->createMock(ContactNotifier::class);
        $notifier->expects(self::never())->method('send');
        $fixture   = IdentityFixture::create([ContactNotifier::class => $notifier]);
        $container = $fixture->container();
        $requests  = $container->get(ContactRequests::class);
        $context   = ContactScenario::token();
        [$contact] = $requests->request(new ContactValue('email', 'ada@example.com'), new VerificationContextToken($context), new VerificationScope(Realm::USER), new VerificationClient('127.0.0.1'));
        $api       = IdentityApiFixture::create($fixture, Realm::USER);
        $response  = $api->request('POST', '/auth/contact-verifications', ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => $context]);
        self::assertSame(200, $response->getStatusCode());
        $state = UserFlowTest::resource($response, 'contactInfo');
        self::assertSame($contact->id(), $state['contactInfoId']);
        self::assertSame('pending', $state['deliveryState']);
    }
}
