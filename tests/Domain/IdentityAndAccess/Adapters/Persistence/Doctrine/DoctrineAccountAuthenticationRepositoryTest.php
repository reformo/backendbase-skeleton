<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\AccountAuthenticationMapper;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class DoctrineAccountAuthenticationRepositoryTest extends TestCase
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
    public function itReadsOnlyActivePrivilegesForAnActiveAccount(): void
    {
        $this->connection->insert('example_accounts', [
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'email' => 'account@example.com',
            'password_hash' => 'password-hash',
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => null,
        ]);
        $accountId      = (int) $this->connection->lastInsertId();
        $addPrivilegeId = $this->insertPrivilege('example.add', null);
        $this->insertPrivilege('example.remove', '2026-08-29 10:00:00.000000');
        $expiredPrivilegeId = $this->insertPrivilege('example.change', null);
        $this->insertAccountPrivilege($accountId, $addPrivilegeId, null);
        $this->insertAccountPrivilege($accountId, $expiredPrivilegeId, '2026-08-29 10:00:00.000000');

        $account = (new DoctrineAccountAuthenticationRepository($this->connection))
            ->findByEmail('account@example.com');

        self::assertNotNull($account);
        self::assertSame('4bb3fe29-8b80-463e-9d42-b3a9298a7586', $account->uuid());
        self::assertSame('password-hash', $account->passwordHash());
        self::assertSame(['example.add'], $account->privileges());
    }

    #[Test]
    public function itDoesNotReadDeletedOrMissingAccounts(): void
    {
        $this->connection->insert('example_accounts', [
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'email' => 'deleted@example.com',
            'password_hash' => 'password-hash',
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => '2026-08-29 10:00:00.000000',
        ]);
        $repository = new DoctrineAccountAuthenticationRepository($this->connection);

        self::assertNull($repository->findByEmail('deleted@example.com'));
        self::assertNull($repository->findByEmail('missing@example.com'));
    }

    #[Test]
    public function itReadsAnAccountWithoutAnActivePrivilege(): void
    {
        $this->connection->insert('example_accounts', [
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'email' => 'account@example.com',
            'password_hash' => 'password-hash',
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => null,
        ]);

        $account = (new DoctrineAccountAuthenticationRepository($this->connection))
            ->findByEmail('account@example.com');

        self::assertNotNull($account);
        self::assertSame([], $account->privileges());
    }

    #[Test]
    public function itRejectsInvalidPersistedAccountData(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AccountAuthenticationMapper::account([
            [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
                'password_hash' => '',
                'privilege_slug' => null,
            ],
        ]);
    }

    private function insertPrivilege(string $slug, string|null $deletedAt): int
    {
        $this->connection->insert('example_privileges', [
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
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
            'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'account_id' => $accountId,
            'privilege_id' => $privilegeId,
            'created_at' => '2026-08-29 10:00:00.000000',
            'expired_at' => $expiredAt,
        ]);
    }
}
