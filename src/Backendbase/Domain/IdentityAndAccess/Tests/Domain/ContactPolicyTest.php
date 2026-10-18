<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ContactVerification as Record;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactPolicyTest extends TestCase
{
    public function testLateDeliveryCannotReplaceTheCurrentGenerationState(): void
    {
        $contact = self::contact();
        $now     = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact->issue('first', 'first-hash', $now);
        $contact->issue('second', 'second-hash', $now);
        $contact->recordDelivery('first', 'accepted');
        self::assertSame('pending', $contact->snapshot()['deliveryState']);
        $contact->recordDelivery('second', 'unknown');
        self::assertSame('unknown', $contact->snapshot()['deliveryState']);
    }

    public function testAConsumedProofCannotBeUsedAgain(): void
    {
        $contact = self::contact();
        $now     = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact->issue('generation', 'hash', $now);
        $contact->confirm(true, $now);
        $contact->consume(IdentityScenario::id(), IdentityScenario::id(), $now);
        $this->expectException(IdentityConflict::class);
        $contact->assertFresh($now);
    }

    public function testStaleContactRevisionCannotReplaceTheCurrentRecord(): void
    {
        $contact = self::contact();
        $record  = Record::create($contact);
        $state   = $contact->snapshot();
        $state['revision']++;
        $this->expectException(IdentityConflict::class);
        $record->synchronize(new ContactInfo(IdentityId::fromString($contact->id()), $state));
    }

    public function testAdminEvidenceMustHaveAnAccountAndActor(): void
    {
        $this->expectException(AuthenticationDenied::class);
        new VerificationScope(Realm::ADMIN);
    }

    #[DataProvider('incompleteOwners')]
    public function testBoundEvidenceRequiresBothOwnerReferences(string|null $accountId, string|null $actorId): void
    {
        $this->expectException(AuthenticationDenied::class);
        new VerificationScope(Realm::USER, ['accountId' => $accountId, 'actorId' => $actorId]);
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function incompleteOwners(): iterable
    {
        yield 'missing Account' => [null, IdentityScenario::id()];
        yield 'missing actor' => [IdentityScenario::id(), null];
    }

    public function testUnknownActionsAreRejected(): void
    {
        $this->expectException(InvalidUserInput::class);
        new ContactAttempt(['action' => 'delete', 'realm' => Realm::USER, 'contactId' => null, 'token' => new VerificationContextToken(ContactScenario::token()), 'client' => new VerificationClient('::1'), 'contact' => null, 'contactType' => null, 'code' => null, 'principal' => null, 'login' => null]);
    }

    public function testPersistedAdminStateWithoutInvitationIsRejected(): void
    {
        $this->expectException(AuthenticationDenied::class);
        new SystemAdmin(IdentityId::create(), ['accountId' => IdentityScenario::id(), 'approved' => false, 'invitation' => null, 'approval' => null, 'deletedAt' => null, 'revision' => 1, 'createdAt' => '2026-10-14 10:00:00.000000']);
    }

    public function testInvalidDirectClientAddressIsRejected(): void
    {
        $this->expectException(InvalidUserInput::class);
        new VerificationClient('invalid');
    }

    private static function contact(): ContactInfo
    {
        return ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), new DateTimeImmutable('2026-10-14T10:00:00Z'));
    }
}
