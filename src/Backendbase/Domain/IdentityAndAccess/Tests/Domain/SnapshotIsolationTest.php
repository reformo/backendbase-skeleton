<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\AccountSnapshot;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ActorSnapshot;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnapshotIsolationTest extends TestCase
{
    public function testAnOldAuthenticationVersionCannotUseAnAcceptedAccount(): void
    {
        $account = self::account();
        $account->acceptRegistration();
        $this->expectException(AuthenticationDenied::class);
        $account->assertSessionAllowed(0, Realm::USER);
    }

    public function testAccountChangesPreserveEarlierSnapshotsAndLegacyRegistration(): void
    {
        $account  = self::account();
        $before   = $account->snapshot();
        $original = $before->toArray();
        $account->verifyOwnership();
        $account->acceptRegistration();
        $account->close(new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        self::assertSame($original, $before->toArray());
        $after          = $account->snapshot();
        $authentication = $after->authentication;
        self::assertTrue($authentication->registrationAccepted);
        self::assertTrue($authentication->ownershipVerified);
        self::assertFalse($authentication->enabled);
        self::assertSame(2, $authentication->authVersion);
        self::assertSame('2026-10-19 10:00:00.123456', $after->deletedAt);
        $original['ownershipVerified'] = true;
        unset($original['registrationAccepted'], $original['firstName'], $original['lastName']);
        $legacy         = AccountSnapshot::fromArray($original);
        $authentication = $legacy->authentication;
        self::assertTrue($authentication->registrationAccepted);
        self::assertNull($legacy->firstName);
        self::assertNull($legacy->lastName);
    }

    public function testActorApprovalAndRemovalPreserveEarlierSnapshots(): void
    {
        $actor    = SystemAdmin::invite(IdentityId::create(), IdentityId::create(), 'invitation', new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $before   = $actor->snapshot();
        $original = $before->toArray();
        $actor->approve('approval');
        $actor->remove(new DateTimeImmutable('2026-10-19T10:01:00Z'));
        $removed = $actor->snapshot();
        $actor->restore();
        $restored = $actor->snapshot();
        self::assertSame($original, $before->toArray());
        self::assertSame('2026-10-19 10:01:00.000000', $removed->deletedAt);
        self::assertNull($restored->deletedAt);
        $authorization = $restored->authorization;
        self::assertTrue($authorization->approved);
        self::assertSame('approval', $authorization->approval);
    }

    public function testContactConfirmationAndConsumptionPreserveEarlierSnapshots(): void
    {
        $now      = new DateTimeImmutable('2026-10-19T10:00:00Z');
        $contact  = ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $now);
        $before   = $contact->snapshot();
        $original = $before->toArray();
        $contact->issue('generation', 'hash', $now);
        $contact->confirm(false, $now);
        $failed = $contact->snapshot();
        $contact->confirm(true, $now);
        $contact->consume('account', 'actor', $now);
        self::assertSame($original, $before->toArray());
        self::assertSame(1, $failed->failedAttempts);
        self::assertNull($failed->verifiedAt);
        self::assertNull($failed->consumedAt);
        $consumed = $contact->snapshot();
        self::assertSame('2026-10-19 10:00:00.000000', $consumed->consumedAt);
    }

    #[DataProvider('invalidSnapshots')]
    public function testAggregateReconstructionRejectsInvalidSnapshotFields(string $kind, string $field, mixed $value): void
    {
        $account  = self::account();
        $snapshot = $account->snapshot();
        $values   = $snapshot->toArray();
        $class    = AccountSnapshot::class;
        if ($kind === 'actor') {
            $actor    = SystemAdmin::invite(IdentityId::create(), IdentityId::create(), 'invitation', new DateTimeImmutable());
            $snapshot = $actor->snapshot();
            $values   = $snapshot->toArray();
            $class    = ActorSnapshot::class;
        }

        $values[$field] = $value;
        $this->expectException(AuthenticationUnavailable::class);
        $class::fromArray($values);
    }

    /** @return iterable<string, array{string, string, mixed}> */
    public static function invalidSnapshots(): iterable
    {
        yield 'unknown Account field' => ['account', 'futureField', true];
        yield 'invalid Account flag' => ['account', 'enabled', 1];
        yield 'invalid Account revision' => ['account', 'revision', '1'];
        yield 'unknown actor field' => ['actor', 'futureField', true];
        yield 'invalid actor flag' => ['actor', 'approved', 1];
        yield 'invalid actor revision' => ['actor', 'revision', '1'];
    }

    private static function account(): Account
    {
        return Account::create(IdentityId::create(), new AccountEmail('ada@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-19T10:00:00Z'));
    }
}
