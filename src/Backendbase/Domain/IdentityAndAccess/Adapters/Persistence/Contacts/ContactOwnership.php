<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Contacts;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentityRecordStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class ContactOwnership
{
    public function __construct(private IdentitySession $session)
    {
    }

    public function assertAllowed(ContactInfo $contact): void
    {
        $state     = $contact->snapshot();
        $accountId = $state->accountId;
        $actorId   = $state->actorId;
        if ($accountId === null || $actorId === null) {
            return;
        }

        $records       = new IdentityRecordStore($this->session);
        $accountRecord = $records->account($accountId, true);
        $records->refreshPersistedUnderLock($accountRecord, true);
        $account = $accountRecord->toDomain();
        $realm   = Realm::from($state->realm);
        $account->assertEligible($realm);
        $actorRecord = $records->actor($realm, $actorId);
        $records->refreshPersistedUnderLock($actorRecord, false);
        $actor = $actorRecord->toDomain();
        $actor->assertContactVerificationAllowed();
        $actor->assertContactOwner($accountId);
    }
}
