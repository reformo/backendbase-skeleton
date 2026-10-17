<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactConfirmation;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ObservedContactStore;
use DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactDeadlineTest extends TestCase
{
    #[DataProvider('deadlines')]
    public function testCodeExpiryIsCheckedAfterTheContactLock(string $advance, bool $duringLock): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        self::assertInstanceOf(Container::class, $container);
        $requests         = $container->get(ContactRequests::class);
        $token            = new VerificationContextToken(ContactScenario::token());
        $scope            = new VerificationScope(Realm::USER);
        [$contact, $code] = $requests->request(new ContactValue('email', 'ada@example.com'), $token, $scope, new VerificationClient('::1'));
        self::assertNotNull($code);
        $reads = 0;
        if ($duringLock) {
            $store = $container->get(ContactStore::class);
            $container->set(ContactStore::class, new ObservedContactStore($store, static function () use ($fixture, $advance, &$reads): void {
                $reads++;
                if ($reads !== 2) {
                    return;
                }

                IdentityScenario::advance($fixture, $advance);
            }));
        }

        if (! $duringLock) {
            IdentityScenario::advance($fixture, $advance);
        }

        $confirmation = $container->get(ContactConfirmation::class);
        try {
            $confirmed = $confirmation->confirm($contact->id(), $token, $scope, $code);
            self::assertSame('PT4M59S', $advance);
            self::assertNotNull($confirmed->snapshot()['verifiedAt']);
        } catch (ContactVerificationExpired) {
            self::assertNotSame('PT4M59S', $advance);
            self::assertNull($fixture->connection()->fetchOne('SELECT verified_at FROM contact_verification'));
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function deadlines(): iterable
    {
        yield 'before expiry' => ['PT4M59S', false];
        yield 'exact expiry' => ['PT5M', false];
        yield 'expiry during lock acquisition' => ['PT5M', true];
    }
}
