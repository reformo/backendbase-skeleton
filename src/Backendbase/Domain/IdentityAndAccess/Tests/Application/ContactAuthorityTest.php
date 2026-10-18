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
    public function testPrincipalRealmMustMatchTheContactVerificationRealm(): void
    {
        $fixture    = IdentityFixture::create();
        $admin      = IdentityScenario::admin($fixture);
        $principal  = new Principal(Realm::USER, ['accountId' => $admin['accountId'], 'actorId' => $admin['actorId'], 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $input      = new ContactAttempt(['action' => 'request', 'realm' => Realm::ADMIN, 'contactId' => null, 'token' => new VerificationContextToken(ContactScenario::token()), 'client' => new VerificationClient('::1'), 'contact' => null, 'contactType' => 'email', 'code' => null, 'principal' => $principal, 'login' => null]);
        $container  = $fixture->container();
        $store      = $container->get(ContactStore::class);
        $authorizer = $container->get(ContactAuthorizer::class);
        $this->expectException(AuthenticationDenied::class);
        $authorizer->resolve($input, $store);
    }

    #[DataProvider('boundaries')]
    public function testAccountAndActorReferencesMustMatch(Realm $realm, bool $persisted): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $other     = IdentityScenario::verified($fixture);
        $actorId   = $realm === Realm::ADMIN ? $admin['actorId'] : $admin['userActorId'];
        $container = $fixture->container();
        $store     = $container->get(ContactStore::class);
        $token     = new VerificationContextToken(ContactScenario::token());
        $scope     = new VerificationScope($realm, ['accountId' => $other['accountId'], 'actorId' => $actorId]);
        $contact   = ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), $token, $scope, new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $this->expectException(AuthenticationDenied::class);
        if ($persisted) {
            $store->transaction(static fn () => $store->save($contact));

            return;
        }

        $principal  = new Principal($realm, ['accountId' => $other['accountId'], 'actorId' => $actorId, 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $input      = new ContactAttempt(['action' => 'request', 'realm' => $realm, 'contactId' => null, 'token' => $token, 'client' => new VerificationClient('::1'), 'contact' => null, 'contactType' => 'email', 'code' => null, 'principal' => $principal, 'login' => null]);
        $authorizer = $container->get(ContactAuthorizer::class);
        $authorizer->resolve($input, $store);
    }

    /** @return iterable<string, array{Realm, bool}> */
    public static function boundaries(): iterable
    {
        yield 'persisted Admin contact' => [Realm::ADMIN, true];
        yield 'authenticated Admin principal' => [Realm::ADMIN, false];
        yield 'persisted User contact' => [Realm::USER, true];
        yield 'authenticated User principal' => [Realm::USER, false];
    }
}
