<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\AccountReadModelMapper;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountReadRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use const DATE_ATOM;

final class DoctrineAccountReadRepositoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE example_accounts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, email TEXT NOT NULL, '
            . 'password_hash TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_privileges ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, title TEXT NOT NULL, '
            . 'slug TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_account_privileged ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, account_id INTEGER NOT NULL, '
            . 'privilege_id INTEGER NOT NULL, created_at TEXT NOT NULL, expired_at TEXT DEFAULT NULL)',
        );
    }

    #[Test]
    public function itListsActiveAccountsWithOnlyTheirActivePrivileges(): void
    {
        $accountId = $this->insertAccount('active@example.com', null);
        $this->insertAccount('deleted@example.com', '2026-08-29 11:00:00.000000');
        $activePrivilegeId  = $this->insertPrivilege('account.list', null);
        $deletedPrivilegeId = $this->insertPrivilege('account.retire', '2026-08-29 11:00:00.000000');
        $this->insertAccountPrivilege($accountId, $activePrivilegeId, null);
        $this->insertAccountPrivilege($accountId, $deletedPrivilegeId, null);

        $accounts = (new DoctrineAccountReadRepository($this->connection))->listActive();

        self::assertCount(1, $accounts);
        self::assertSame('active@example.com', $accounts[0]->email());
        self::assertSame(['account.list'], $accounts[0]->privilegeSlugs());
        self::assertSame('2026-08-29T10:00:00+00:00', $accounts[0]->createdAt()->format(DATE_ATOM));
    }

    #[Test]
    public function itRejectsMalformedPersistedAccountListData(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountReadModelMapper::list([
            [
                'uuid' => '',
                'email' => 'account@example.com',
                'created_at' => '2026-08-29 10:00:00.000000',
                'privilege_slug' => null,
            ],
        ]);
    }

    #[Test]
    public function itRejectsAnInvalidPersistedAccountCreationDate(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountReadModelMapper::list([
            [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
                'created_at' => 'invalid-date',
                'privilege_slug' => null,
            ],
        ]);
    }

    private function insertAccount(string $email, string|null $deletedAt): int
    {
        $this->connection->insert('example_accounts', [
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a758' . ($deletedAt === null ? '6' : '7'),
            'email' => $email,
            'password_hash' => 'password-hash',
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertPrivilege(string $slug, string|null $deletedAt): int
    {
        $this->connection->insert('example_privileges', [
            'uuid' => '5bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'title' => $slug,
            'slug' => $slug,
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAccountPrivilege(int $accountId, int $privilegeId, string|null $expiredAt): void
    {
        $this->connection->insert('example_account_privileged', [
            'uuid' => '6bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'account_id' => $accountId,
            'privilege_id' => $privilegeId,
            'created_at' => '2026-08-29 10:00:00.000000',
            'expired_at' => $expiredAt,
        ]);
    }
}
