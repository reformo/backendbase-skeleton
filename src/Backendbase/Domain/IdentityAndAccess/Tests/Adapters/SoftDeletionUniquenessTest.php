<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SoftDeletionUniquenessTest extends TestCase
{
    public function testAccountClosureReleasesEmailAndMobileForAnotherAccount(): void
    {
        $fixture   = IdentityFixture::create();
        $first     = IdentityScenario::registered($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static function () use ($store, $first): void {
            $account = $store->accountForUpdate($first['accountId']);
            $account->close(new DateTimeImmutable('2026-10-14T10:01:00Z'));
            $store->saveAccount($account);
        });
        $second = IdentityScenario::registered($fixture);
        self::assertNotSame($first['accountId'], $second['accountId']);
        $connection = $fixture->connection();
        self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_account WHERE active_email IS NOT NULL AND active_mobile IS NOT NULL'));
        self::assertSame('+905551234567', $connection->fetchOne('SELECT normalized_mobile FROM identity_account WHERE deleted_at IS NOT NULL'));
    }

    public function testActorRemovalDoesNotReleaseTheAccountMobile(): void
    {
        $fixture   = IdentityFixture::create();
        $first     = IdentityScenario::registered($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static function () use ($store, $first): void {
            $actor = $store->actorForUpdate(Realm::USER, $first['actorId']);
            $actor->remove(new DateTimeImmutable('2026-10-14T10:01:00Z'));
            $store->saveActor($actor);
        });
        $this->expectException(IdentityConflict::class);
        IdentityScenario::registered($fixture, '+905551234567', 'second@example.com');
    }

    public function testDuplicateMobileDoesNotCreateAnotherAccount(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        try {
            IdentityScenario::registered($fixture, '+905551234567', 'second@example.com');
            self::fail('An active Account mobile cannot be reused.');
        } catch (IdentityConflict $exception) {
            self::assertSame('An active Account already uses this mobile.', $exception->getMessage());
        }

        $connection = $fixture->connection();
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_user'));
        self::assertSame(4, $connection->fetchOne('SELECT COUNT(*) FROM contact_info'));
    }

    public function testDatabaseConstraintRejectsDuplicateMobileWithoutTheRegistrationPrecheck(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $account   = Account::create(IdentityId::create(), new AccountEmail('second@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable());
        $this->expectException(IdentityConflict::class);
        $store->transaction(static fn () => $store->saveAccount($account));
    }
}
