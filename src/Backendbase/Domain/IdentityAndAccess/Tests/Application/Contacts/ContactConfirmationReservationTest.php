<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactChecks;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactConfirmation;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ObservedContactStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function range;

final class ContactConfirmationReservationTest extends TestCase
{
    /** @param class-string<ContactVerificationExpired|RuntimeException> $expected */
    #[DataProvider('postReservationFailures')]
    public function testFailureAfterReservationKeepsTheQuotaAndDoesNotVerify(string $failure, string $expected): void
    {
        $fixture          = IdentityFixture::create();
        $container        = $fixture->container();
        $requests         = $container->get(ContactRequests::class);
        $value            = new ContactValue('email', 'ada@example.com');
        $token            = new VerificationContextToken(ContactScenario::token());
        $scope            = new VerificationScope(Realm::USER);
        [$contact, $code] = $requests->request($value, $token, $scope, new VerificationClient('127.0.0.1'));
        self::assertInstanceOf(VerificationCode::class, $code);
        $store        = $container->get(ContactStore::class);
        $checks       = $container->get(ContactChecks::class);
        $reads        = 0;
        $observed     = new ObservedContactStore($store, static function () use ($fixture, $failure, &$reads): void {
            $reads++;
            if ($reads !== 2) {
                return;
            }

            if ($failure === 'expiry') {
                IdentityScenario::advance($fixture, 'PT5M');

                return;
            }

            throw new RuntimeException('Interrupted after the reservation.');
        });
        $confirmation = new ContactConfirmation($observed, $checks);
        $contactId    = $contact->id();
        try {
            $confirmation->confirm($contactId, $token, $scope, $code);
            self::fail('Confirmation must fail after the reservation.');
        } catch (ContactVerificationExpired | RuntimeException $exception) {
            self::assertInstanceOf($expected, $exception);
        }

        self::assertSame(2, $reads);
        $connection = $fixture->connection();
        self::assertNull($connection->fetchOne('SELECT verified_at FROM contact_verification'));
        self::assertSame(0, $connection->fetchOne("SELECT json_extract(state, '$.failedAttempts') FROM contact_verification"));
        $limiter = $container->get(VerificationLimiter::class);
        foreach (range(1, 9) as $attempt) {
            $limiter->attempt($value);
        }

        $this->expectException(VerificationLimited::class);
        $limiter->attempt($value);
    }

    /** @return iterable<string, array{string, class-string<ContactVerificationExpired|RuntimeException>}> */
    public static function postReservationFailures(): iterable
    {
        yield 'deadline crosses during second read' => ['expiry', ContactVerificationExpired::class];
        yield 'store operation is interrupted' => ['interruption', RuntimeException::class];
    }

    public function testSuccessfulConfirmationReleasesOnlyItsOwnReservation(): void
    {
        $fixture          = IdentityFixture::create();
        $container        = $fixture->container();
        $requests         = $container->get(ContactRequests::class);
        $limiter          = $container->get(VerificationLimiter::class);
        $value            = new ContactValue('email', 'ada@example.com');
        $token            = new VerificationContextToken(ContactScenario::token());
        $scope            = new VerificationScope(Realm::USER);
        [$contact, $code] = $requests->request($value, $token, $scope, new VerificationClient('127.0.0.1'));
        self::assertInstanceOf(VerificationCode::class, $code);
        foreach (range(1, 9) as $attempt) {
            $limiter->attempt($value);
        }

        $confirmation = $container->get(ContactConfirmation::class);
        $contactId    = $contact->id();
        $confirmed    = $confirmation->confirm($contactId, $token, $scope, $code);
        $state        = $confirmed->snapshot();
        self::assertNotNull($state->verifiedAt);
        $limiter->attempt($value);
        $this->expectException(VerificationLimited::class);
        $limiter->attempt($value);
    }
}
