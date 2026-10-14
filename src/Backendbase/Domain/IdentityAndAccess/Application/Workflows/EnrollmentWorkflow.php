<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

use function random_int;
use function sprintf;

final readonly class EnrollmentWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationTools $tools)
    {
    }

    public function verifyOwnership(Realm $realm, ProofInput $input): void
    {
        $currentStore = $this->store;

        $accepted = $currentStore->transaction(function () use ($realm, $input): bool {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $challenge               = $currentStore->challenge($input->challengeId());
            [$account, , $challenge] = ChallengeContext::load($this->store, $challenge, $realm);
            $accepted                = $currentTools->verify($challenge, $realm, ChallengePurpose::OWNERSHIP, $input);
            $currentStore->saveChallenge($challenge);
            if ($accepted) {
                $account->verifyOwnership();
                $currentStore->saveAccount($account);
            }

            return $accepted;
        });
        self::assertAccepted($accepted);
    }

    /** @return array{challengeId: string, expiresIn: int} */
    public function resendOwnership(string $operationId, Realm $realm, LoginInput $input): array
    {
        $currentStore = $this->store;
        $currentTools = $this->tools;

        $code  = sprintf('%06d', random_int(0, 999999));
        $email = $currentStore->transaction(function () use ($operationId, $realm, $input, $code): AccountEmail {
            $currentStore = $this->store;
            $currentTools = $this->tools;

            $actor = $currentStore->actorByLogin($realm, $input->loginName());
            if ($actor === null) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $state        = $actor->snapshot();
            $account      = $currentStore->account($state['accountId']);
            $accountState = $account->snapshot();
            $account->assertActive();
            $currentTools->assertPassword($account->id(), $input->password());
            if ($accountState['owned']) {
                throw AuthenticationDenied::create('Ownership is already verified.');
            }

            $account->revokeCredentials();
            $currentStore->saveAccount($account);
            $challenge = $currentTools->challenge($operationId, $account, $actor, ChallengePurpose::OWNERSHIP, $code);
            $currentStore->saveChallenge($challenge);

            return new AccountEmail($accountState['email']);
        });
        $currentTools->sendOwnership($operationId, $email, $code);

        return ['challengeId' => $operationId, 'expiresIn' => 300];
    }

    private static function assertAccepted(bool $accepted): void
    {
        if (! $accepted) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }
}
