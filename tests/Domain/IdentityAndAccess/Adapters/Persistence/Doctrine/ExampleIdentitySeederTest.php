<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Seeders\ExampleIdentitySeeder;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

final class ExampleIdentitySeederTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE example_accounts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, email TEXT NOT NULL UNIQUE, '
            . 'password_hash TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_privileges ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, '
            . 'created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_account_privileged ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, account_id INTEGER NOT NULL, '
            . 'privilege_id INTEGER NOT NULL, created_at TEXT NOT NULL, expired_at TEXT DEFAULT NULL, '
            . 'UNIQUE(account_id, privilege_id))',
        );
    }

    #[Test]
    public function itSeedsTheExampleAccountAndPrivilegesIdempotently(): void
    {
        $seeder = new ExampleIdentitySeeder();

        $seeder->seed($this->connection);
        $seeder->seed($this->connection);

        $account = $this->connection->fetchAssociative(
            'SELECT password_hash FROM example_accounts WHERE email = :email',
            ['email' => 'mehmet@mkorkmaz.com'],
        );
        self::assertIsArray($account);
        self::assertTrue(
            PasswordHash::create((string) $account['password_hash'])
                ->verifyHash(new SensitiveParameterValue('mirmir')),
        );
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_accounts'));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_privileges'));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_account_privileged'));
        self::assertSame(
            ['example.add', 'example.change', 'example.remove'],
            $this->connection->fetchFirstColumn('SELECT slug FROM example_privileges ORDER BY slug ASC'),
        );
    }
}
