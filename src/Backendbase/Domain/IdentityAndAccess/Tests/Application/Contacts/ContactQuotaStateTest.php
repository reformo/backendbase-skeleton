<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactIssuance;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function range;

final class ContactQuotaStateTest extends TestCase
{
    public function testNormalizedContactBudgetIsSharedAcrossUserAndAdminRealms(): void
    {
        $fixture      = IdentityFixture::create();
        $admin        = IdentityScenario::admin($fixture);
        $container    = $fixture->container();
        $requests     = $container->get(ContactRequests::class);
        $user         = new VerificationScope(Realm::USER);
        $owner        = ['accountId' => $admin['accountId'], 'actorId' => $admin['actorId']];
        $adminScope   = new VerificationScope(Realm::ADMIN, $owner);
        $userContact  = new ContactValue('email', 'ADA@EXAMPLE.COM');
        $adminContact = new ContactValue('email', 'ada@example.com');
        $client       = new VerificationClient('127.0.0.7');
        $otherClient  = new VerificationClient('127.0.0.8');
        $requests->request($userContact, new VerificationContextToken(ContactScenario::token()), $user, $client);
        try {
            $requests->request($adminContact, new VerificationContextToken(ContactScenario::token()), $adminScope, $otherClient);
            self::fail('The Admin request must share the contact cooldown.');
        } catch (VerificationLimited $exception) {
            self::assertSame(['retryAfter' => 60], $exception->context());
        }

        foreach ([$adminScope, $user, $adminScope, $user] as $scope) {
            IdentityScenario::advance($fixture, 'PT60S');
            $requests->request($adminContact, new VerificationContextToken(ContactScenario::token()), $scope, $otherClient);
        }

        IdentityScenario::advance($fixture, 'PT60S');
        $this->expectException(VerificationLimited::class);
        $requests->request($adminContact, new VerificationContextToken(ContactScenario::token()), $adminScope, $otherClient);
    }

    public function testAWinningConcurrentRequestStillConsumesTheReservedSendBudget(): void
    {
        $fixture     = IdentityFixture::create();
        $container   = $fixture->container();
        $value       = new ContactValue('email', 'ada@example.com');
        $token       = new VerificationContextToken(ContactScenario::token());
        $scope       = new VerificationScope(Realm::USER);
        $client      = new VerificationClient('127.0.0.1');
        $winner      = ContactInfo::request(IdentityId::create(), $value, $token, $scope, new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $store       = $this->createStub(ContactStore::class);
        $transaction = $store->method('transaction');
        $transaction->willReturnCallback(static fn (callable $work): mixed => $work());
        $slot = $store->method('slot');
        $slot->willReturnOnConsecutiveCalls(null, null, $winner);
        $save = $store->method('save');
        $save->willThrowException(IdentityConflict::create('Concurrent insertion.'));
        $issuance         = $container->get(ContactIssuance::class);
        $simulatedRequest = new ContactRequests($store, $issuance);
        [$actual, $code]  = $simulatedRequest->request($value, $token, $scope, $client);
        $winnerId         = $winner->id();
        self::assertSame($winnerId, $actual->id());
        self::assertNull($code);
        $requests = $container->get(ContactRequests::class);
        foreach (range(1, 4) as $attempt) {
            IdentityScenario::advance($fixture, 'PT60S');
            $requests->request($value, new VerificationContextToken(ContactScenario::token()), $scope, $client);
        }

        IdentityScenario::advance($fixture, 'PT60S');
        $this->expectException(VerificationLimited::class);
        $requests->request($value, new VerificationContextToken(ContactScenario::token()), $scope, $client);
    }
}
