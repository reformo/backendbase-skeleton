<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RealmEligibilityTest extends TestCase
{
    #[DataProvider('realmEligibilityCases')]
    public function testRealmEligibilitySurvivesReconstruction(
        bool $registrationAccepted,
        bool $ownershipVerified,
        Realm $realm,
        bool $allowed,
    ): void {
        $id      = IdentityId::create();
        $account = Account::create($id, new AccountEmail('account@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-19T10:00:00Z'));
        if ($registrationAccepted) {
            $account->acceptRegistration();
        }

        if ($ownershipVerified) {
            $account->verifyOwnership();
        }

        $snapshot      = $account->snapshot();
        $serialized    = $snapshot->toArray();
        $reconstructed = Account::reconstitute($id, $serialized);
        $roundTrip     = $reconstructed->snapshot();
        self::assertSame($serialized, $roundTrip->toArray());
        if (! $allowed) {
            $this->expectException(AuthenticationDenied::class);
        }

        $reconstructed->assertSessionAllowed(1, $realm);
        $after = $reconstructed->snapshot();
        self::assertSame($serialized, $after->toArray());
    }

    /** @return iterable<string, array{bool, bool, Realm, bool}> */
    public static function realmEligibilityCases(): iterable
    {
        yield 'User without acceptance or ownership' => [false, false, Realm::USER, false];
        yield 'User with ownership only' => [false, true, Realm::USER, false];
        yield 'User with acceptance only' => [true, false, Realm::USER, true];
        yield 'User with acceptance and ownership' => [true, true, Realm::USER, true];
        yield 'Admin without acceptance or ownership' => [false, false, Realm::ADMIN, false];
        yield 'Admin with ownership only' => [false, true, Realm::ADMIN, true];
        yield 'Admin with acceptance only' => [true, false, Realm::ADMIN, false];
        yield 'Admin with acceptance and ownership' => [true, true, Realm::ADMIN, true];
    }

    #[DataProvider('realms')]
    public function testClosureDeniesEligibleAccountsInEachRealm(Realm $realm): void
    {
        $id      = IdentityId::create();
        $account = Account::create($id, new AccountEmail('closed@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $account->acceptRegistration();
        $account->verifyOwnership();
        $account->close(new DateTimeImmutable('2026-10-19T11:00:00Z'));
        $snapshot       = $account->snapshot();
        $authentication = $snapshot->authentication;
        self::assertFalse($authentication->enabled);
        self::assertSame(2, $authentication->authVersion);
        $reconstructed = Account::reconstitute($id, $snapshot->toArray());
        $this->expectException(AuthenticationDenied::class);
        $reconstructed->assertSessionAllowed(2, $realm);
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'User' => [Realm::USER];
        yield 'Admin' => [Realm::ADMIN];
    }

    #[DataProvider('inactiveStates')]
    public function testDisabledOrDeletedAccountsCannotAuthenticate(bool $enabled, string|null $deletedAt, Realm $realm): void
    {
        $id      = IdentityId::create();
        $account = Account::create($id, new AccountEmail('inactive@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $account->acceptRegistration();
        $account->verifyOwnership();
        $snapshot           = $account->snapshot();
        $state              = $snapshot->toArray();
        $state['enabled']   = $enabled;
        $state['deletedAt'] = $deletedAt;
        $reconstructed      = Account::reconstitute($id, $state);
        $this->expectException(AuthenticationDenied::class);
        $reconstructed->assertSessionAllowed(1, $realm);
    }

    /** @return iterable<string, array{bool, string|null, Realm}> */
    public static function inactiveStates(): iterable
    {
        yield 'disabled User Account' => [false, null, Realm::USER];
        yield 'disabled Admin Account' => [false, null, Realm::ADMIN];
        yield 'deleted User Account' => [true, '2026-10-19 11:00:00.000000', Realm::USER];
        yield 'deleted Admin Account' => [true, '2026-10-19 11:00:00.000000', Realm::ADMIN];
    }

    #[DataProvider('closedMutations')]
    public function testClosedAccountRejectsMutationsWithoutChangingState(Closure $operation): void
    {
        $id      = IdentityId::create();
        $account = Account::create($id, new AccountEmail('closed@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $account->close(new DateTimeImmutable('2026-10-19T11:00:00Z'));
        $before = $account->snapshot();
        $this->expectException(AuthenticationDenied::class);
        try {
            $operation($account);
        } finally {
            $previous = $before->toArray();
            $after    = $account->snapshot();
            self::assertSame($previous, $after->toArray());
        }
    }

    /** @return iterable<string, array{Closure(Account): void}> */
    public static function closedMutations(): iterable
    {
        yield 'accept registration' => [static fn (Account $account) => $account->acceptRegistration()];
        yield 'verify ownership' => [static fn (Account $account) => $account->verifyOwnership()];
        yield 'close again' => [static fn (Account $account) => $account->close(new DateTimeImmutable('2026-10-19T12:00:00Z'))];
    }
}
