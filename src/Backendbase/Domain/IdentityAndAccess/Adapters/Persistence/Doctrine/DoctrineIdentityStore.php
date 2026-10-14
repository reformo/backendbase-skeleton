<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

use function array_unique;
use function sort;

use const SORT_STRING;

final readonly class DoctrineIdentityStore implements IdentityStore
{
    public function __construct(private IdentityRecordStore $records, private DoctrineIdentityTransaction $unit)
    {
    }

    public function transaction(callable $work): mixed
    {
        $currentUnit = $this->unit;

        return $currentUnit->run($work);
    }

    public function account(string $id): Account
    {
        $currentRecords = $this->records;

        $record = $currentRecords->account($id);

        return $record->toDomain();
    }

    public function accountForUpdate(string $id): Account
    {
        $records = $this->records;
        $record  = $records->account($id, true);

        return $record->toDomain();
    }

    public function protectActorMutation(string $observerAccountId, string $targetAccountId): void
    {
        $currentRecords = $this->records;

        $ids = array_unique([$observerAccountId, $targetAccountId]);
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            $currentRecords->account($id, $id === $targetAccountId);
        }
    }

    public function accountByEmail(AccountEmail $email): Account|null
    {
        $currentRecords = $this->records;

        $record = $currentRecords->activeBy(IdentityAccount::class, ['activeEmail' => $email->toString()]);

        return $record?->toDomain();
    }

    public function actor(Realm $realm, string $id): Actor
    {
        $currentRecords = $this->records;

        $actors = $currentRecords->actors();

        return $actors->actor($realm, $id);
    }

    public function actorReference(Realm $realm, string $id): Actor
    {
        $currentRecords = $this->records;

        $actors = $currentRecords->actors();

        return $actors->actorReference($realm, $id);
    }

    public function actorForUpdate(Realm $realm, string $id): Actor
    {
        $currentRecords = $this->records;

        $actors = $currentRecords->actors();

        return $actors->actorForUpdate($realm, $id);
    }

    public function actorByLogin(Realm $realm, LoginName $name): Actor|null
    {
        $currentRecords = $this->records;

        $actors = $currentRecords->actors();

        return $actors->actorByLogin($realm, $name);
    }

    public function challenge(string $id): Challenge
    {
        $records    = $this->records;
        $challenges = new IdentityChallengeRecords($records);

        return $challenges->get($id, false);
    }

    public function challengeForUpdate(string $id): Challenge
    {
        $records    = $this->records;
        $challenges = new IdentityChallengeRecords($records);

        return $challenges->get($id, true);
    }

    public function saveAccount(Account $account): void
    {
        $records = $this->records;
        $record  = $records->find(IdentityAccount::class, $account->id(), true);
        if ($record === null) {
            $records->persist(IdentityAccount::create($account));

            return;
        }

        $record->synchronize($account);
    }

    public function saveActor(Actor $actor): void
    {
        $currentRecords = $this->records;

        $actors = $currentRecords->actors();
        $actors->saveActor($actor);
    }

    public function saveChallenge(Challenge $challenge): void
    {
        $records    = $this->records;
        $challenges = new IdentityChallengeRecords($records);
        $challenges->save($challenge);
    }
}
