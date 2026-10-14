<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;

final readonly class DoctrineCredentialStore implements CredentialStore
{
    public function __construct(private IdentityRecordStore $records)
    {
    }

    /** @return array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} */
    public function read(string $accountId): array
    {
        $currentRecords = $this->records;

        $record  = $currentRecords->account($accountId, true);
        $account = $record->toDomain();
        $account->assertActive();

        return $record->credentials();
    }

    /** @param array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} $credentials */
    public function save(string $accountId, array $credentials): void
    {
        $currentRecords = $this->records;

        $record  = $currentRecords->account($accountId, true);
        $account = $record->toDomain();
        $account->assertActive();
        $record->replaceCredentials($credentials);
    }
}
