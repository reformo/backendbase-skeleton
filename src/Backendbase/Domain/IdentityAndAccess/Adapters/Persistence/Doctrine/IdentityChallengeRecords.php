<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ChallengeRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class IdentityChallengeRecords
{
    public function __construct(private IdentityRecordStore $records)
    {
    }

    public function get(string $id, bool $write): Challenge
    {
        $records = $this->records;
        $record  = $records->find(ChallengeRecord::class, $id, $write ? true : null);
        if ($record === null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        if ($write) {
            $records->refreshUnderLock($record, true);
        }

        return $record->toDomain();
    }

    public function save(Challenge $challenge): void
    {
        $currentRecords = $this->records;

        $state         = $challenge->snapshot();
        $accountRecord = $currentRecords->account($state['accountId']);
        $account       = $accountRecord->toDomain();
        $account->assertActive();
        $actors     = $currentRecords->actors();
        $actor      = $actors->actor(Realm::from($state['realm']), $state['actorId']);
        $actorState = $actor->snapshot();
        if ($actorState['accountId'] !== $state['accountId'] || $actorState['deletedAt'] !== null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $records = $this->records;
        $record  = $records->find(ChallengeRecord::class, $challenge->id(), true);
        if ($record === null) {
            $records->persist(ChallengeRecord::create($challenge));

            return;
        }

        $record->synchronize($challenge);
    }
}
