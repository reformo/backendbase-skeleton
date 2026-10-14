<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;

final readonly class AuthenticationTools
{
    public function __construct(private Credentials $credentials, private Challenges $challenges)
    {
    }

    public function hashPassword(Password $password): string
    {
        $currentCredentials = $this->credentials;

        return $currentCredentials->hashPassword($password);
    }

    public function initializeCredentials(string $accountId, string $hash): void
    {
        $currentCredentials = $this->credentials;

        $currentCredentials->initialize($accountId, $hash);
    }

    public function assertPassword(string $accountId, Password $password): void
    {
        $currentCredentials = $this->credentials;

        $currentCredentials->assertPassword($accountId, $password);
    }

    public function challenge(string $id, Account $account, Actor $actor, ChallengePurpose $purpose, string $proof): Challenge
    {
        $currentCredentials = $this->credentials;
        $currentChallenges  = $this->challenges;

        $hash = $currentCredentials->proofHash($proof);

        return $currentChallenges->create($id, $account, $actor, $purpose, $hash);
    }

    public function verify(Challenge $challenge, Realm $realm, ChallengePurpose $purpose, ProofInput $input): bool
    {
        $currentChallenges  = $this->challenges;
        $currentCredentials = $this->credentials;

        $state = $challenge->snapshot();
        $now   = $currentChallenges->now();
        if (! $challenge->permitsAttempt($purpose, $realm, $now)) {
            return false;
        }

        $valid = $currentCredentials->verifyProof($input->code(), $state['proofHash']);

        return $challenge->verify($purpose, $realm, $valid, $now);
    }

    public function sendOwnership(string $id, AccountEmail $email, string $code): void
    {
        $currentChallenges = $this->challenges;

        $currentChallenges->sendOwnership($id, $email, $code);
    }

    public function now(): DateTimeImmutable
    {
        $currentChallenges = $this->challenges;

        return $currentChallenges->now();
    }

    public function sessionExpiresAt(): DateTimeImmutable
    {
        $currentChallenges = $this->challenges;

        return $currentChallenges->sessionExpiresAt();
    }
}
