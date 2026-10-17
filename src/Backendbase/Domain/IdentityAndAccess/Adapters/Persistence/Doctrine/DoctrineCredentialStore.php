<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;

final readonly class DoctrineCredentialStore implements CredentialStore
{
    public function __construct(private IdentityRecordStore $records)
    {
    }

    public function read(string $accountId): string
    {
        $currentRecords = $this->records;

        $record  = $currentRecords->account($accountId, true);
        $account = $record->toDomain();
        $account->assertActive();

        return $record->passwordHash();
    }

    public function save(string $accountId, string $passwordHash): void
    {
        $currentRecords = $this->records;

        $record  = $currentRecords->account($accountId, true);
        $account = $record->toDomain();
        $account->assertActive();
        $record->replacePasswordHash($passwordHash);
    }
}
