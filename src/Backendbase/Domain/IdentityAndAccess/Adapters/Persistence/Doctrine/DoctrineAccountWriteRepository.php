<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository as AccountWriteRepositoryContract;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Helpers\DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;

use function count;

final readonly class DoctrineAccountWriteRepository implements AccountWriteRepositoryContract
{
    public function __construct(private Connection $connection)
    {
    }

    public function register(Account $account): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($account): void {
                $this->rejectExistingEmail($connection, $account);
                $privilegeIds = $this->activePrivilegeIds($connection, $account->privileges());
                $connection->insert('example_accounts', [
                    'uuid' => $account->id()->toString(),
                    'email' => $account->email()->toString(),
                    'password_hash' => $account->passwordHash()->toString(),
                    'created_at' => $this->now(),
                    'deleted_at' => null,
                ]);
                $accountId = AccountPersistenceMapper::positiveInteger($connection->lastInsertId(), 'account_id');
                $this->synchronizePrivileges($connection, $accountId, $privilegeIds);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw AccountAlreadyRegistered::create('An account already uses this email address.', previous: $exception);
        }
    }

    public function getActive(AccountId $accountId): Account
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT account.uuid, account.email, account.password_hash, privilege.slug AS privilege_slug '
            . 'FROM example_accounts account '
            . 'LEFT JOIN example_account_privileged accountPrivilege '
            . 'ON accountPrivilege.account_id = account.id AND accountPrivilege.expired_at IS NULL '
            . 'LEFT JOIN example_privileges privilege '
            . 'ON privilege.id = accountPrivilege.privilege_id AND privilege.deleted_at IS NULL '
            . 'WHERE account.uuid = :uuid AND account.deleted_at IS NULL '
            . 'ORDER BY privilege.slug ASC',
            ['uuid' => $accountId->toString()],
        );
        if ($rows === []) {
            throw ResourceNotFound::create('The account does not exist.');
        }

        return AccountPersistenceMapper::account($rows);
    }

    public function save(Account $account): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($account): void {
                $accountId    = $this->activeAccountId($connection, $account->id());
                $privilegeIds = $this->activePrivilegeIds($connection, $account->privileges());
                $connection->update('example_accounts', [
                    'email' => $account->email()->toString(),
                    'password_hash' => $account->passwordHash()->toString(),
                ], ['id' => $accountId]);
                $this->synchronizePrivileges($connection, $accountId, $privilegeIds);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw AccountAlreadyRegistered::create('An account already uses this email address.', previous: $exception);
        }
    }

    public function retire(Account $account): void
    {
        $updated = $this->connection->executeStatement(
            'UPDATE example_accounts SET deleted_at = :deletedAt '
            . 'WHERE uuid = :uuid AND deleted_at IS NULL',
            ['deletedAt' => $this->now(), 'uuid' => $account->id()->toString()],
        );
        if ($updated === 0) {
            throw ResourceNotFound::create('The account does not exist.');
        }
    }

    /** @return list<int> */
    private function activePrivilegeIds(Connection $connection, AccountPrivileges $privileges): array
    {
        $slugs = $privileges->slugs();
        if ($slugs === []) {
            return [];
        }

        $rows = $connection->executeQuery(
            'SELECT id, slug FROM example_privileges '
            . 'WHERE slug IN (:slugs) AND deleted_at IS NULL '
            . 'ORDER BY slug ASC',
            ['slugs' => $slugs],
            ['slugs' => ArrayParameterType::STRING],
        )->fetchAllAssociative();
        if (count($rows) !== count($slugs)) {
            throw UnknownAccountPrivilege::create('An account privilege is unavailable.');
        }

        $privilegeIds = [];
        foreach ($rows as $row) {
            $privilegeIds[] = AccountPersistenceMapper::positiveInteger($row['id'] ?? null, 'privilege_id');
        }

        return $privilegeIds;
    }

    private function activeAccountId(Connection $connection, AccountId $accountId): int
    {
        $id = $connection->fetchOne(
            'SELECT id FROM example_accounts WHERE uuid = :uuid AND deleted_at IS NULL',
            ['uuid' => $accountId->toString()],
        );
        if ($id === false) {
            throw ResourceNotFound::create('The account does not exist.');
        }

        return AccountPersistenceMapper::positiveInteger($id, 'account_id');
    }

    private function rejectExistingEmail(Connection $connection, Account $account): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM example_accounts WHERE email = :email',
            ['email' => $account->email()->toString()],
        );
        if ($exists !== false) {
            throw AccountAlreadyRegistered::create('An account already uses this email address.');
        }
    }

    /** @param list<int> $privilegeIds */
    private function synchronizePrivileges(Connection $connection, int $accountId, array $privilegeIds): void
    {
        $connection->executeStatement(
            'UPDATE example_account_privileged SET expired_at = :expiredAt '
            . 'WHERE account_id = :accountId AND expired_at IS NULL',
            ['expiredAt' => $this->now(), 'accountId' => $accountId],
        );
        foreach ($privilegeIds as $privilegeId) {
            $reactivated = $connection->executeStatement(
                'UPDATE example_account_privileged SET expired_at = NULL '
                . 'WHERE account_id = :accountId AND privilege_id = :privilegeId',
                ['accountId' => $accountId, 'privilegeId' => $privilegeId],
            );
            if ($reactivated !== 0) {
                continue;
            }

            $connection->insert('example_account_privileged', [
                'uuid' => Uuid::uuid7()->toString(),
                'account_id' => $accountId,
                'privilege_id' => $privilegeId,
                'created_at' => $this->now(),
                'expired_at' => null,
            ]);
        }
    }

    private function now(): string
    {
        return DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
    }
}
