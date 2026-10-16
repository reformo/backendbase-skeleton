<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\Registration\RegistrationCompletion;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;

final readonly class RegistrationWorkflow
{
    public function __construct(private IdentityStore $store, private RegistrationCompletion $completion)
    {
    }

    /** @return array{accountId: string, actorId: string} */
    public function register(string $operationId, RegistrationInput $input): array
    {
        $completion   = $this->completion;
        $currentStore = $this->store;

        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $password  = $input->password();
        $hash      = $completion->hashPassword($password);
        $currentStore->transaction(function () use ($input, $accountId, $actorId, $hash): void {
            $currentStore = $this->store;
            $completion   = $this->completion;

            $email  = $input->email();
            $mobile = $input->mobile();
            if ($currentStore->accountByEmail($email) !== null) {
                throw IdentityConflict::create('An active Account already uses this email.');
            }

            if ($currentStore->accountByMobile($mobile) !== null) {
                throw IdentityConflict::create('An active Account already uses this mobile.');
            }

            $completion->complete($currentStore, $input, $accountId, $actorId, $hash);
        });

        return ['accountId' => $accountId->toString(), 'actorId' => $actorId->toString()];
    }
}
