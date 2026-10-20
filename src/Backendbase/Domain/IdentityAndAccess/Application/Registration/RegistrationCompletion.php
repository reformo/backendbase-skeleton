<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;

final readonly class RegistrationCompletion
{
    public function __construct(private IdentityStore $store, private RegistrationEvidence $evidence)
    {
    }

    public function complete(Credentials $credentials, RegistrationInput $input, IdentityId $accountId, IdentityId $actorId, string $hash): void
    {
        $store = $this->store;
        $store->transaction(function () use ($store, $credentials, $input, $accountId, $actorId, $hash): void {
            $email  = $input->email();
            $mobile = $input->mobile();
            if ($store->accountByEmail($email) !== null) {
                throw IdentityConflict::create('An active Account already uses this email.');
            }

            if ($store->accountByMobile($mobile) !== null) {
                throw IdentityConflict::create('An active Account already uses this mobile.');
            }

            $this->createIdentity($credentials, $input, $accountId, $actorId, $hash);
        });
    }

    private function createIdentity(Credentials $credentials, RegistrationInput $input, IdentityId $accountId, IdentityId $actorId, string $hash): void
    {
        $store            = $this->store;
        $evidence         = $this->evidence;
        [$contacts, $now] = $evidence->verify($input);
        $email            = $input->email();
        $mobile           = $input->mobile();
        $names            = $input->names();
        $account          = Account::create($accountId, $email, $mobile, $now, $names);
        $account->acceptRegistration();
        if ($contacts !== []) {
            $account->verifyOwnership();
        }

        $actor = User::create($actorId, $accountId, $now);
        $store->saveAccount($account);
        $store->saveActor($actor);
        $accountIdentifier = $account->id();
        $actorIdentifier   = $actor->id();
        $credentials->initialize($accountIdentifier, $hash);
        $evidence->consume($contacts, $accountIdentifier, $actorIdentifier, $now);
    }
}
