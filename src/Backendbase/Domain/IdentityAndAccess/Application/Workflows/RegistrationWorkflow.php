<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\Registration\RegistrationPreparation;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;

final readonly class RegistrationWorkflow
{
    public function __construct(private IdentityStore $store, private RegistrationPreparation $tools)
    {
    }

    /** @return array{accountId: string, actorId: string} */
    public function register(string $operationId, RegistrationInput $input): array
    {
        $currentTools = $this->tools;
        $currentStore = $this->store;

        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $hash      = $currentTools->hashPassword($input->password());
        $currentStore->transaction(function () use ($input, $accountId, $actorId, $hash): void {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $email  = $input->email();
            $mobile = $input->mobile();
            if ($currentStore->accountByEmail($email) !== null) {
                throw IdentityConflict::create('An active Account already uses this email.');
            }

            if ($currentStore->accountByMobile($mobile) !== null) {
                throw IdentityConflict::create('An active Account already uses this mobile.');
            }

            [$contacts, $now] = $currentTools->verify($input);
            $names            = $input->names();
            $account          = Account::create($accountId, $email, $mobile, $now, $names);
            $account->verifyOwnership();
            $actor = User::create($actorId, $accountId, $now);
            $currentStore->saveAccount($account);
            $currentStore->saveActor($actor);
            $currentTools->initializeCredentials($account->id(), $hash);
            $currentTools->consume($contacts, $account->id(), $actor->id(), $now);
        });

        return ['accountId' => $accountId->toString(), 'actorId' => $actorId->toString()];
    }
}
