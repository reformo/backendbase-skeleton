<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactAuthorizer;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactAuthorityTest extends TestCase
{
    #[DataProvider('boundaries')]
    public function testAccountAndAdminReferencesMustMatch(bool $persisted): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $other     = IdentityScenario::verified($fixture);
        $container = $fixture->container();
        $store     = $container->get(ContactStore::class);
        $token     = new VerificationContextToken(ContactScenario::token());
        $scope     = new VerificationScope(Realm::ADMIN, ['accountId' => $other['accountId'], 'actorId' => $admin['actorId']]);
        $contact   = ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), $token, $scope, new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $this->expectException(AuthenticationDenied::class);
        if ($persisted) {
            $store->transaction(static fn () => $store->save($contact));

            return;
        }

        $principal  = new Principal(Realm::ADMIN, ['accountId' => $other['accountId'], 'actorId' => $admin['actorId'], 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $input      = new ContactAttempt(['action' => 'request', 'realm' => Realm::ADMIN, 'contactId' => null, 'token' => $token, 'client' => new VerificationClient('::1'), 'contact' => null, 'contactType' => 'email', 'code' => null, 'principal' => $principal, 'login' => null]);
        $authorizer = $container->get(ContactAuthorizer::class);
        $authorizer->resolve($input, $store);
    }

    /** @return iterable<string, array{bool}> */
    public static function boundaries(): iterable
    {
        yield 'persisted contact' => [true];
        yield 'authenticated principal' => [false];
    }
}
