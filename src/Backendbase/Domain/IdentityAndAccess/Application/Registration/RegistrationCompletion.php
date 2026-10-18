<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;

final readonly class RegistrationCompletion
{
    public function __construct(private Credentials $credentials, private RegistrationEvidence $evidence)
    {
    }

    public function hashPassword(Password $password): string
    {
        $credentials = $this->credentials;

        return $credentials->hashPassword($password);
    }

    public function complete(IdentityStore $store, RegistrationInput $input, IdentityId $accountId, IdentityId $actorId, string $hash): void
    {
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
        $credentials       = $this->credentials;
        $accountIdentifier = $account->id();
        $actorIdentifier   = $actor->id();
        $credentials->initialize($accountIdentifier, $hash);
        $evidence->consume($contacts, $accountIdentifier, $actorIdentifier, $now);
    }
}
