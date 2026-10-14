<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;

use function random_int;
use function sprintf;

final readonly class RegistrationWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationTools $tools)
    {
    }

    /** @return array{accountId: string, actorId: string, challengeId: string, expiresIn: int} */
    public function register(string $operationId, RegistrationInput $input): array
    {
        $currentTools = $this->tools;
        $currentStore = $this->store;

        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $code      = sprintf('%06d', random_int(0, 999999));
        $hash      = $currentTools->hashPassword($input->password());
        $currentStore->transaction(function () use ($input, $accountId, $actorId, $code, $hash, $operationId): void {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            if ($currentStore->accountByEmail($input->email()) !== null) {
                throw IdentityConflict::create('An active Account already uses this email.');
            }

            $now     = $currentTools->now();
            $account = Account::create($accountId, $input->email(), $now);
            $actor   = User::create($actorId, $accountId, $input->loginName(), $now);
            $currentStore->saveAccount($account);
            $currentStore->saveActor($actor);
            $currentTools->initializeCredentials($account->id(), $hash);
            $challenge = $currentTools->challenge($operationId, $account, $actor, ChallengePurpose::OWNERSHIP, $code);
            $currentStore->saveChallenge($challenge);
        });
        $currentTools->sendOwnership($operationId, $input->email(), $code);

        return ['accountId' => $accountId->toString(), 'actorId' => $actorId->toString(), 'challengeId' => $operationId, 'expiresIn' => 300];
    }
}
