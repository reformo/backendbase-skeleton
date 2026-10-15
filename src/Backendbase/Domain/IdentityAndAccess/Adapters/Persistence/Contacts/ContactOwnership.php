<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Contacts;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentityRecordStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class ContactOwnership
{
    public function __construct(private IdentitySession $session)
    {
    }

    public function assertAllowed(ContactInfo $contact): void
    {
        $state = $contact->snapshot();
        if ($state['accountId'] === null || $state['actorId'] === null) {
            return;
        }

        $records       = new IdentityRecordStore($this->session);
        $accountRecord = $records->account($state['accountId'], true);
        $records->refreshPersistedUnderLock($accountRecord, true);
        $account      = $accountRecord->toDomain();
        $accountState = $account->snapshot();
        $account->assertSessionAllowed($accountState['authVersion']);
        $realm       = Realm::from($state['realm']);
        $actorRecord = $records->actor($realm, $state['actorId']);
        $records->refreshPersistedUnderLock($actorRecord, false);
        $actor = $actorRecord->toDomain();
        $actor->assertContactVerificationAllowed();
        $actorState = $actor->snapshot();
        if ($actorState['accountId'] !== $state['accountId']) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }
    }
}
