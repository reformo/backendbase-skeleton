<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\AdminRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\UserRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;

final readonly class IdentityActorRecords
{
    public function __construct(private IdentityRecordStore $records)
    {
    }

    public function actor(Realm $realm, string $id): Actor
    {
        $currentRecords = $this->records;

        $record = $currentRecords->actor($realm, $id);
        $currentRecords->refreshPersistedUnderLock($record, false);

        return $record->toDomain();
    }

    public function actorReference(Realm $realm, string $id): Actor
    {
        $currentRecords = $this->records;

        $record = $currentRecords->find(IdentityRecordStore::actorClass($realm), $id, null);
        if ($record === null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        return $record->toDomain();
    }

    public function actorForUpdate(Realm $realm, string $id): Actor
    {
        $records = $this->records;
        $record  = $records->actor($realm, $id, true);
        $records->refreshUnderLock($record, true);

        return $record->toDomain();
    }

    public function actorByLogin(Realm $realm, LoginName $name): Actor|null
    {
        $records = $this->records;
        $record  = $records->activeBy(IdentityRecordStore::actorClass($realm), ['activeLoginName' => $name->toString()], false);
        if ($record === null) {
            return null;
        }

        $actor   = $record->toDomain();
        $state   = $actor->snapshot();
        $account = $this->accountForUpdate($state['accountId']);
        $account->assertActive();
        $records->refreshUnderLock($record, false);
        $actor = $record->toDomain();
        $state = $actor->snapshot();

        return $state['deletedAt'] === null ? $actor : null;
    }

    public function saveActor(Actor $actor): void
    {
        $currentRecords = $this->records;

        $state         = $actor->snapshot();
        $accountRecord = $currentRecords->account($state['accountId']);
        $account       = $accountRecord->toDomain();
        $account->assertActive();
        $records = $this->records;
        $record  = $records->find(IdentityRecordStore::actorClass($actor->realm()), $actor->id(), true);
        if ($record === null) {
            $record = $actor instanceof User ? UserRecord::create($actor) : AdminRecord::create(self::admin($actor));
            $records->persist($record);

            return;
        }

        $record->synchronize($actor);
    }

    private function accountForUpdate(string $id): Account
    {
        $currentRecords = $this->records;

        $record = $currentRecords->account($id, true);

        return $record->toDomain();
    }

    private static function admin(Actor $actor): SystemAdmin
    {
        if (! $actor instanceof SystemAdmin) {
            throw AuthenticationDenied::create('The actor type is invalid.');
        }

        return $actor;
    }
}
