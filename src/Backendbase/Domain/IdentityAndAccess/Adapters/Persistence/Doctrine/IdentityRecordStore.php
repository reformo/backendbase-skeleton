<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Account as AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Admin as AdminRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\User as UserRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ramsey\Uuid\Uuid;

use function array_unique;
use function sort;

use const SORT_STRING;

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
     * @template TRecord of AccountRecord|AdminRecord|UserRecord
     */
    public function find(string $class, string $id, bool|null $write = false): AccountRecord|AdminRecord|UserRecord|null
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $connection = $manager->getConnection();
        $lock       = $write === null || $connection->getDatabasePlatform() instanceof SQLitePlatform ? null : ($write ? LockMode::PESSIMISTIC_WRITE : LockMode::PESSIMISTIC_READ);

        $identifier = Uuid::fromString($id);
        $unitOfWork = $manager->getUnitOfWork();
        $managed    = $unitOfWork->tryGetById(['id' => $identifier], $class);
        if ($managed instanceof $class && $lock !== null) {
            $manager->lock($managed, $lock);

            return $managed;
        }

        return $manager->find($class, $identifier, $lock);
    }

    public function protectActorMutation(string $observerAccountId, string $targetAccountId): void
    {
        $ids = array_unique([$observerAccountId, $targetAccountId]);
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            $this->account($id, $id === $targetAccountId);
        }
    }

    public function account(string $id, bool $write = false): AccountRecord
    {
        return $this->find(AccountRecord::class, $id, $write) ?? throw AuthenticationDenied::create('Authentication is not permitted.');
    }

    public function actor(Realm $realm, string $id, bool $write = false): AdminRecord|UserRecord
    {
        return $this->find(self::actorClass($realm), $id, $write) ?? throw AuthenticationDenied::create('Authentication is not permitted.');
    }

    /**
     * @param class-string<TRecord> $class
     * @param array<string, mixed>  $criteria
     *
     * @return TRecord|null
     *
     * @template TRecord of AccountRecord|AdminRecord|UserRecord
     */
    public function activeBy(string $class, array $criteria, bool $lock = true): AccountRecord|AdminRecord|UserRecord|null
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

    public function refreshUnderLock(AccountRecord|AdminRecord|UserRecord $record, bool $write): void
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $connection = $manager->getConnection();
        $lock       = $connection->getDatabasePlatform() instanceof SQLitePlatform ? null : ($write ? LockMode::PESSIMISTIC_WRITE : LockMode::PESSIMISTIC_READ);
        $manager->refresh($record, $lock);
    }

    public function refreshPersistedUnderLock(AccountRecord|AdminRecord|UserRecord $record, bool $write): void
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $unitOfWork = $manager->getUnitOfWork();
        if ($unitOfWork->isScheduledForInsert($record)) {
            return;
        }

        $this->refreshUnderLock($record, $write);
    }

    public function persist(AccountRecord|AdminRecord|UserRecord $record): void
    {
        $session = $this->session;
        $manager = $session->manager();
        $manager->persist($record);
    }

    /** @return class-string<UserRecord>|class-string<AdminRecord> */
    public static function actorClass(Realm $realm): string
    {
        return $realm === Realm::USER ? UserRecord::class : AdminRecord::class;
    }
}
