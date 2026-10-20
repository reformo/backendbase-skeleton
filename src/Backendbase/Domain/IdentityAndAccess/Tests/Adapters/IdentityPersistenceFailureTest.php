<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Account as AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Admin as AdminRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\User as UserRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use DateTimeImmutable;
use Doctrine\ORM\Configuration;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityPersistenceFailureTest extends TestCase
{
    public function testDuplicateActiveEmailRollsBackTheWholeRegistration(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        $this->expectException(IdentityConflict::class);
        IdentityScenario::registered($fixture, '+905551234568');
    }

    public function testActorWritersRejectOrphanParentsAndMissingReferences(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        try {
            $store->transaction(static fn () => $store->actorReference(Realm::ADMIN, IdentityScenario::id()));
            self::fail('A missing actor reference must fail.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }

        $actor = User::create(IdentityId::create(), IdentityId::create(), new DateTimeImmutable());
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn () => $store->saveActor($actor));
    }

    #[DataProvider('actorRealms')]
    public function testActorAccountReferenceCannotChangeAndStaleRevisionsCannotWrite(Realm $realm): void
    {
        $now         = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $actor       = $realm === Realm::USER
            ? User::create(IdentityId::create(), IdentityId::create(), $now)
            : SystemAdmin::invite(IdentityId::create(), IdentityId::create(), 'invitation', $now);
        $record      = $actor instanceof User ? UserRecord::create($actor) : AdminRecord::create($actor);
        $replacement = $actor instanceof User
            ? new User(IdentityId::fromString($actor->id()), [...$actor->snapshot()->toArray(), 'accountId' => IdentityScenario::id()])
            : new SystemAdmin(IdentityId::fromString($actor->id()), [...$actor->snapshot()->toArray(), 'accountId' => IdentityScenario::id()]);
        try {
            $record->synchronize($replacement);
            self::fail('The Account reference must be immutable.');
        } catch (IdentityConflict $exception) {
            self::assertSame('The actor Account reference cannot change.', $exception->getMessage());
        }

        $account       = Account::create(IdentityId::create(), new AccountEmail('test@example.com'), new AccountMobile('+905551234567'), $now);
        $accountRecord = AccountRecord::create($account);
        $this->expectException(IdentityConflict::class);
        $accountRecord->assertRevision(2);
    }

    /** @return iterable<string, array{Realm}> */
    public static function actorRealms(): iterable
    {
        yield 'User' => [Realm::USER];
        yield 'Admin' => [Realm::ADMIN];
    }

    public function testIdentityTransactionsCannotNest(): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $store      = $container->get(IdentityStore::class);
        $connection = $fixture->connection();
        $connection->beginTransaction();
        try {
            $this->expectException(LogicException::class);
            $store->transaction(static fn (): bool => true);
        } finally {
            $connection->rollBack();
        }
    }

    public function testRecordAccessRequiresAnActiveIdentityTransaction(): void
    {
        $session = new IdentitySession(new EntityManagerFactory(new Configuration()));
        $this->expectException(LogicException::class);
        $session->manager();
    }
}
