<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final class ChallengeContext
{
    /** @return array{Account, Actor, Challenge} */
    public static function load(IdentityStore $store, Challenge $challenge, Realm $realm): array
    {
        $state = $challenge->snapshot();
        if ($state['realm'] !== $realm->value) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $account = $store->accountForUpdate($state['accountId']);
        $account->assertActive();
        $accountState = $account->snapshot();
        $actor        = $store->actor($realm, $state['actorId']);
        $actorState   = $actor->snapshot();
        if ($actorState['accountId'] !== $state['accountId'] || $actorState['deletedAt'] !== null || $accountState['authVersion'] !== $state['authVersion']) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $lockedChallenge = $store->challengeForUpdate($challenge->id());

        return [$account, $actor, $lockedChallenge];
    }
}
