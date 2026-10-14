<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final readonly class LoginWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationTools $tools)
    {
    }

    /** @return array{challengeId: string, expiresIn: int} */
    public function begin(string $operationId, Realm $realm, LoginInput $input): array
    {
        $currentStore = $this->store;

        $currentStore->transaction(function () use ($operationId, $realm, $input): void {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $actor = $currentStore->actorByLogin($realm, $input->loginName());
            if ($actor === null) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $actor->assertSessionAllowed();
            $state        = $actor->snapshot();
            $account      = $currentStore->account($state['accountId']);
            $accountState = $account->snapshot();
            $account->assertSessionAllowed($accountState['authVersion']);
            $currentTools->assertPassword($account->id(), $input->password(), true);
            $challenge = $currentTools->challenge($operationId, $account, $actor, ChallengePurpose::LOGIN);
            $currentStore->saveChallenge($challenge);
        });

        return ['challengeId' => $operationId, 'expiresIn' => 300];
    }

    /** @return array{Principal, DateTimeImmutable} */
    public function complete(Realm $realm, ProofInput $input): array
    {
        $currentStore = $this->store;
        $currentTools = $this->tools;

        $principal = $currentStore->transaction(function () use ($realm, $input): Principal|null {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $challenge                     = $currentStore->challenge($input->challengeId());
            [$account, $actor, $challenge] = ChallengeContext::load($this->store, $challenge, $realm);
            $state                         = $account->snapshot();
            $account->assertSessionAllowed($state['authVersion']);
            $actor->assertSessionAllowed();
            $accepted = $currentTools->verify($challenge, $realm, ChallengePurpose::LOGIN, $input);
            $currentStore->saveChallenge($challenge);
            if (! $accepted) {
                return null;
            }

            $sid = IdentityId::create();
            $jti = IdentityId::create();

            return new Principal($realm, ['accountId' => $account->id(), 'actorId' => $actor->id(), 'sid' => $sid->toString(), 'jti' => $jti->toString(), 'authVersion' => $state['authVersion']]);
        });
        if ($principal === null) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        return [$principal, $currentTools->sessionExpiresAt()];
    }
}
