<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class ContactDeliveryStateTest extends TestCase
{
    public function testAValidCodeCanConfirmAContactAfterAnUnknownDeliveryOutcome(): void
    {
        $deliveredCode = null;
        $notifier      = $this->createMock(ContactNotifier::class);
        $expectation   = $notifier->expects(self::once());
        $method        = $expectation->method('send');
        $method->willReturnCallback(static function (string $generationId, ContactValue $contact, string $code) use (&$deliveredCode): void {
            $deliveredCode = $code;

            throw new RuntimeException('The provider outcome is unknown.');
        });
        $fixture = IdentityFixture::create([ContactNotifier::class => $notifier]);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $token   = ContactScenario::token();
        $request = ['contactType' => 'email', 'contactValue' => 'ada@example.com', 'verificationContextToken' => $token];
        $failed  = $api->request('POST', '/auth/contact-verifications', $request);
        self::assertSame(503, $failed->getStatusCode());
        self::assertIsString($deliveredCode);
        $connection = $fixture->connection();
        $id         = $connection->fetchOne('SELECT uuid FROM contact_verification');
        self::assertIsString($id);
        self::assertSame('unknown', $connection->fetchOne("SELECT json_extract(state, '$.deliveryState') FROM contact_verification"));
        $response = $api->request('POST', '/auth/contact-verifications/' . $id . '/confirm', ['verificationContextToken' => $token, 'code' => $deliveredCode]);
        self::assertSame(200, $response->getStatusCode());
        $result = UserFlowTest::resource($response, 'contactInfo');
        self::assertSame('unknown', $result['deliveryState']);
        self::assertIsString($result['verifiedAt']);
        self::assertIsString($result['registrationValidUntil']);
        self::assertSame(0, $connection->fetchOne("SELECT json_extract(state, '$.failedAttempts') FROM contact_verification"));
    }
}
