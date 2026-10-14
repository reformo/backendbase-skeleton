<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\MfaAttemptBudget;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use DateTimeImmutable;

final readonly class Credentials
{
    public function __construct(private CredentialStore $store, private AuthenticationSecrets $secrets)
    {
    }

    public function hashPassword(Password $password): string
    {
        $currentSecrets = $this->secrets;

        return $currentSecrets->hashPassword($password);
    }

    public function initialize(string $accountId, string $passwordHash): void
    {
        $currentStore = $this->store;

        $currentStore->save($accountId, ['passwordHash' => $passwordHash, 'totpSecret' => null, 'pendingTotpSecret' => null, 'lastTotpStep' => -1, 'failedMfaAttempts' => 0, 'blockedUntil' => null]);
    }

    public function assertPassword(string $accountId, Password $password, bool $requireMfa = false): void
    {
        $currentStore   = $this->store;
        $currentSecrets = $this->secrets;

        $credentials = $currentStore->read($accountId);
        if (! $currentSecrets->verifyPassword($password, $credentials['passwordHash']) || ($requireMfa && $credentials['totpSecret'] === null)) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }
    }

    public function prepareTotp(string $accountId): string
    {
        $currentStore   = $this->store;
        $currentSecrets = $this->secrets;

        $credentials = $currentStore->read($accountId);
        if ($credentials['totpSecret'] !== null) {
            throw AuthenticationDenied::create('A factor is already enrolled. Controlled recovery is required.');
        }

        $secret                           = $currentSecrets->newTotpSecret();
        $credentials['pendingTotpSecret'] = $currentSecrets->encrypt($secret);
        $currentStore->save($accountId, $credentials);

        return $secret;
    }

    public function factorStep(string $accountId, string $code, bool $enrollment, string $binding, DateTimeImmutable $now): int|null
    {
        $currentStore   = $this->store;
        $currentSecrets = $this->secrets;

        $credentials = $currentStore->read($accountId);
        $budget      = MfaAttemptBudget::reconstitute($credentials['failedMfaAttempts'], $credentials['blockedUntil']);
        if (! $budget->permitsAttempt($now)) {
            return null;
        }

        $currentStore->save($accountId, [...$credentials, ...$budget->snapshot()]);
        $encrypted = $enrollment ? $credentials['pendingTotpSecret'] : $credentials['totpSecret'];
        if ($encrypted === null) {
            return null;
        }

        $secret = $currentSecrets->decrypt($encrypted);
        if ($enrollment && ! $currentSecrets->verifyProof($secret, $binding)) {
            return null;
        }

        return $currentSecrets->totpStep($secret, $code, $credentials['lastTotpStep'], $now);
    }

    public function consumeFactor(string $accountId, int $step, bool $enrollment): void
    {
        $currentStore = $this->store;

        $credentials = $currentStore->read($accountId);
        if ($step <= $credentials['lastTotpStep']) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $credentials['lastTotpStep']      = $step;
        $credentials['failedMfaAttempts'] = 0;
        $credentials['blockedUntil']      = null;
        if ($enrollment) {
            $credentials['totpSecret']        = $credentials['pendingTotpSecret'];
            $credentials['pendingTotpSecret'] = null;
        }

        $currentStore->save($accountId, $credentials);
    }

    public function recordFactorFailure(string $accountId, DateTimeImmutable $now): void
    {
        $currentStore = $this->store;

        $credentials = $currentStore->read($accountId);
        $budget      = MfaAttemptBudget::reconstitute($credentials['failedMfaAttempts'], $credentials['blockedUntil']);
        $budget->failed($now);
        $currentStore->save($accountId, [...$credentials, ...$budget->snapshot()]);
    }

    public function proofHash(string $proof): string
    {
        $currentSecrets = $this->secrets;

        return $currentSecrets->proofHash($proof);
    }

    public function verifyProof(string $proof, string $expected): bool
    {
        $currentSecrets = $this->secrets;

        return $currentSecrets->verifyProof($proof, $expected);
    }
}
