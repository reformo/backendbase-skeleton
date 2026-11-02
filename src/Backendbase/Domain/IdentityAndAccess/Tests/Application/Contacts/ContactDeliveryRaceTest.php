<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactDelivery;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactDeliveryRaceTest extends TestCase
{
    #[DataProvider('providerOutcomes')]
    public function testLateProviderResultCannotChangeAResentGeneration(bool $providerFails): void
    {
        $notifier         = $this->createMock(ContactNotifier::class);
        $fixture          = IdentityFixture::create([ContactNotifier::class => $notifier]);
        $container        = $fixture->container();
        $requests         = $container->get(ContactRequests::class);
        $token            = new VerificationContextToken(ContactScenario::token());
        $scope            = new VerificationScope(Realm::USER);
        $client           = new VerificationClient('127.0.0.1');
        [$contact, $code] = $requests->request(new ContactValue('email', 'ada@example.com'), $token, $scope, $client);
        self::assertInstanceOf(VerificationCode::class, $code);
        $contactId   = $contact->id();
        $first       = $contact->snapshot();
        $replacement = null;
        $expectation = $notifier->expects(self::once());
        $method      = $expectation->method('send');
        $method->willReturnCallback(static function (string $generationId, ContactValue $destination, string $deliveryCode) use ($fixture, $requests, $contactId, $token, $scope, $client, $providerFails, &$replacement): void {
            IdentityScenario::advance($fixture, 'PT60S');
            [$resent]    = $requests->resend($contactId, $token, $scope, $client);
            $state       = $resent->snapshot();
            $replacement = $state->generationId;
            if ($providerFails) {
                throw new RuntimeException('The provider outcome is unknown.');
            }
        });
        $delivery = $container->get(ContactDelivery::class);
        try {
            $delivery->send($contact, $code);
            self::assertFalse($providerFails);
        } catch (AuthenticationUnavailable $exception) {
            self::assertTrue($providerFails);
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }

        self::assertIsString($replacement);
        self::assertNotSame($first->generationId, $replacement);
        $connection = $fixture->connection();
        self::assertSame($replacement, $connection->fetchOne("SELECT json_extract(state, '$.generationId') FROM contact_verification"));
        self::assertSame('pending', $connection->fetchOne("SELECT json_extract(state, '$.deliveryState') FROM contact_verification"));
        self::assertNull($connection->fetchOne('SELECT verified_at FROM contact_verification'));
    }

    /** @return iterable<string, array{bool}> */
    public static function providerOutcomes(): iterable
    {
        yield 'late acceptance' => [false];
        yield 'late unknown result' => [true];
    }
}
