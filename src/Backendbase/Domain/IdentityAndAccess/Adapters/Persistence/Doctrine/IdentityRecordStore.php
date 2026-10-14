<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ActorRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentifiedRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAdmin;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityUser;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ramsey\Uuid\Uuid;

final readonly class IdentityRecordStore
{
    private IdentityActorRecords $actors;

    public function __construct(private IdentitySession $session)
    {
        $this->actors = new IdentityActorRecords($this);
    }

    public function actors(): IdentityActorRecords
    {
        return $this->actors;
    }

    /**
     * @param class-string<TRecord> $class
     *
     * @return TRecord|null
     *
     * @template TRecord of IdentifiedRecord
     */
    public function find(string $class, string $id, bool|null $write = false): IdentifiedRecord|null
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $connection = $manager->getConnection();
        $lock       = $write === null || $connection->getDatabasePlatform() instanceof SQLitePlatform ? null : ($write ? LockMode::PESSIMISTIC_WRITE : LockMode::PESSIMISTIC_READ);

        $identifier = Uuid::fromString($id);
        $unit       = $manager->getUnitOfWork();
        $managed    = $unit->tryGetById(['id' => $identifier], $class);
        if ($managed instanceof $class && $lock !== null) {
            $manager->lock($managed, $lock);

            return $managed;
        }

        return $manager->find($class, $identifier, $lock);
    }

    public function account(string $id, bool $write = false): IdentityAccount
    {
        return $this->find(IdentityAccount::class, $id, $write) ?? throw AuthenticationDenied::create('Authentication is not permitted.');
    }

    public function actor(Realm $realm, string $id, bool $write = false): ActorRecord
    {
        return $this->find(self::actorClass($realm), $id, $write) ?? throw AuthenticationDenied::create('Authentication is not permitted.');
    }

    /**
     * @param class-string<TRecord> $class
     * @param array<string, mixed>  $criteria
     *
     * @return TRecord|null
     *
     * @template TRecord of IdentifiedRecord
     */
    public function activeBy(string $class, array $criteria, bool $lock = true): IdentifiedRecord|null
    {
        $criteria['deletedAt'] = null;
        $session               = $this->session;
        $manager               = $session->manager();
        $repository            = $manager->getRepository($class);
        $record                = $repository->findOneBy($criteria);
        if ($record === null) {
            return null;
        }

        return $lock ? $this->find($class, $record->id()) : $record;
    }

    public function refreshUnderLock(IdentifiedRecord $record, bool $write): void
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $connection = $manager->getConnection();
        $lock       = $connection->getDatabasePlatform() instanceof SQLitePlatform ? null : ($write ? LockMode::PESSIMISTIC_WRITE : LockMode::PESSIMISTIC_READ);
        $manager->refresh($record, $lock);
    }

    public function refreshPersistedUnderLock(IdentifiedRecord $record, bool $write): void
    {
        $session = $this->session;
        $manager = $session->manager();
        $unit    = $manager->getUnitOfWork();
        if ($unit->isScheduledForInsert($record)) {
            return;
        }

        $this->refreshUnderLock($record, $write);
    }

    public function persist(IdentifiedRecord $record): void
    {
        $session = $this->session;
        $manager = $session->manager();
        $manager->persist($record);
    }

    /** @return class-string<IdentityUser>|class-string<IdentityAdmin> */
    public static function actorClass(Realm $realm): string
    {
        return $realm === Realm::USER ? IdentityUser::class : IdentityAdmin::class;
    }
}
